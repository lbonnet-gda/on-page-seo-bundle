<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Crawler;

use Lbonnet\CrawlerToolkit\Http\BoundedContentReader;
use Lbonnet\CrawlerToolkit\Http\EffectiveUrlResolver;
use Lbonnet\CrawlerToolkit\Http\ThrottleExemptionInterface;
use Lbonnet\CrawlerToolkit\Robots\RobotsTxtCheckerInterface;
use Lbonnet\CrawlerToolkit\Url\UrlNormalizer;
use Lbonnet\OnPageSeoBundle\Auditor\DuplicateContentAuditorInterface;
use Lbonnet\OnPageSeoBundle\Auditor\PageAuditorInterface;
use Lbonnet\OnPageSeoBundle\Extractor\InternalLinkExtractorInterface;
use Lbonnet\OnPageSeoBundle\Extractor\PageMetadataExtractorInterface;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;
use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class SiteCrawler implements CrawlerInterface
{
    private const MAX_HTML_LENGTH = 5_000_000;

    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; OnPageSeoBundle/1.0; +https://github.com/lbonnet-gda/on-page-seo-bundle)';

    public function __construct(
        private readonly InternalLinkExtractorInterface $linkExtractor,
        private readonly PageMetadataExtractorInterface $metadataExtractor,
        private readonly PageAuditorInterface $auditor,
        private readonly DuplicateContentAuditorInterface $duplicateContentAuditor,
        private readonly HttpClientInterface $httpClient,
        private readonly ?RobotsTxtCheckerInterface $robotsTxtChecker = null,
        private readonly int $defaultMaxDepth = 3,
        private readonly int $defaultTimeout = 10,
        private readonly string $userAgent = self::DEFAULT_USER_AGENT,
        /** @var list<string> */
        private readonly array $defaultExcludePatterns = [],
    ) {
    }

    public function crawl(
        string $startUrl,
        ?int $maxDepth = null,
        array $excludePatterns = [],
        ?callable $progressCallback = null,
    ): SeoAuditReport {
        $startTime = microtime(true);
        $maxDepth = $maxDepth ?? $this->defaultMaxDepth;
        $activeExcludePatterns = array_merge($this->defaultExcludePatterns, $excludePatterns);

        $visited = [];
        $pages = [];
        $totalChecked = 0;

        /** @var list<array{url: string, depth: int}> $queue */
        $queue = [['url' => $startUrl, 'depth' => 0]];

        $startHost = parse_url($startUrl, PHP_URL_HOST);
        $throttle = null;

        if (is_string($startHost) && $this->httpClient instanceof ThrottleExemptionInterface) {
            $throttle = $this->httpClient;

            $crawlDelay = $this->robotsTxtChecker?->crawlDelay($startUrl);
            $delayMs = $crawlDelay !== null ? (int)round($crawlDelay * 1000) : 0;

            $throttle->setHostDelay($startHost, $delayMs);
        }

        try {
            while (!empty($queue)) {
                $item = array_shift($queue);
                $url = $item['url'];
                $depth = $item['depth'];

                $visitedKey = UrlNormalizer::normalizeForDedup($url);

                if (isset($visited[$visitedKey])) {
                    continue;
                }

                $visited[$visitedKey] = true;

                try {
                    $response = $this->httpClient->request(Request::METHOD_GET, $url, [
                        'timeout' => $this->defaultTimeout,
                        'headers' => [
                            'User-Agent' => $this->userAgent,
                        ],
                    ]);

                    /** @var array<string, list<string>> $headers */
                    $headers = $response->getHeaders(false);
                    $effectiveUrl = EffectiveUrlResolver::resolve($response, $url);
                    $contentType = $headers['content-type'][0] ?? null;

                    if ($contentType !== null && !str_contains($contentType, 'text/html')) {
                        continue;
                    }

                    $html = BoundedContentReader::read($this->httpClient, $response, self::MAX_HTML_LENGTH);
                } catch (Throwable) {
                    continue;
                }

                $effectiveKey = UrlNormalizer::normalizeForDedup($effectiveUrl);

                if ($effectiveKey !== $visitedKey) {
                    if (isset($visited[$effectiveKey])) {
                        continue;
                    }

                    $visited[$effectiveKey] = true;
                }

                $totalChecked++;

                $metadata = $this->metadataExtractor->extract($html);
                $issues = $this->auditor->audit($metadata);

                $pages[] = new PageAudit(url: $effectiveUrl, metadata: $metadata, issues: $issues);

                if ($progressCallback !== null) {
                    $progressCallback($effectiveUrl, $totalChecked, count($issues));
                }

                if ($depth >= $maxDepth) {
                    continue;
                }

                $discoveredLinks = $this->linkExtractor->extract($html, $effectiveUrl, $activeExcludePatterns);

                foreach ($discoveredLinks as $link) {
                    if ($link->isExternal || isset($visited[UrlNormalizer::normalizeForDedup($link->url)])) {
                        continue;
                    }

                    if ($this->robotsTxtChecker?->isAllowed($link->url) === false) {
                        continue;
                    }

                    $queue[] = ['url' => $link->url, 'depth' => $depth + 1];
                }
            }
        } finally {
            $throttle?->setHostDelay(null);
        }

        $pages = $this->duplicateContentAuditor->audit($pages);

        $totalDuration = microtime(true) - $startTime;

        return new SeoAuditReport(
            startUrl: $startUrl,
            pages: $pages,
            totalChecked: $totalChecked,
            totalDuration: round($totalDuration, 3),
        );
    }
}

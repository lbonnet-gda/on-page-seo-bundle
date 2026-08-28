<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Crawler;

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
    public function __construct(
        private readonly InternalLinkExtractorInterface $linkExtractor,
        private readonly PageMetadataExtractorInterface $metadataExtractor,
        private readonly PageAuditorInterface $auditor,
        private readonly DuplicateContentAuditorInterface $duplicateContentAuditor,
        private readonly HttpClientInterface $httpClient,
        private readonly int $defaultMaxDepth = 3,
        private readonly int $defaultTimeout = 10,
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

        while (!empty($queue)) {
            $item = array_shift($queue);
            $url = $item['url'];
            $depth = $item['depth'];

            if (isset($visited[$url])) {
                continue;
            }

            $visited[$url] = true;

            try {
                $response = $this->httpClient->request(Request::METHOD_GET, $url, [
                    'timeout' => $this->defaultTimeout,
                ]);

                /** @var array<string, list<string>> $headers */
                $headers = $response->getHeaders(false);
                $contentType = $headers['content-type'][0] ?? null;

                if ($contentType !== null && !str_contains($contentType, 'text/html')) {
                    continue;
                }

                $html = $response->getContent();
            } catch (Throwable) {
                continue;
            }

            $totalChecked++;

            $metadata = $this->metadataExtractor->extract($html);
            $issues = $this->auditor->audit($metadata);

            $pages[] = new PageAudit(url: $url, metadata: $metadata, issues: $issues);

            if ($progressCallback !== null) {
                $progressCallback($url, $totalChecked, count($issues));
            }

            if ($depth >= $maxDepth) {
                continue;
            }

            $discoveredLinks = $this->linkExtractor->extract($html, $url, $activeExcludePatterns);

            foreach ($discoveredLinks as $link) {
                if ($link->isExternal || isset($visited[$link->url])) {
                    continue;
                }

                $queue[] = ['url' => $link->url, 'depth' => $depth + 1];
            }
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

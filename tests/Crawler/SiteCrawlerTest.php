<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\Crawler;

use Lbonnet\CrawlerToolkit\Http\ThrottleExemptionInterface;
use Lbonnet\CrawlerToolkit\Robots\RobotsTxtCheckerInterface;
use Lbonnet\OnPageSeoBundle\Auditor\DuplicateContentAuditor;
use Lbonnet\OnPageSeoBundle\Auditor\PageAuditorInterface;
use Lbonnet\OnPageSeoBundle\Crawler\SiteCrawler;
use Lbonnet\OnPageSeoBundle\Extractor\InternalLinkExtractorInterface;
use Lbonnet\OnPageSeoBundle\Extractor\PageMetadataExtractorInterface;
use Lbonnet\OnPageSeoBundle\Model\DiscoveredLink;
use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

final class SiteCrawlerTest extends TestCase
{
    public function testCrawlAuditsInternalPagesAndSkipsExternalOnes(): void
    {
        $startUrl = 'https://example.com';

        $linkExtractor = $this->createMock(InternalLinkExtractorInterface::class);
        $linkExtractor->method('extract')->willReturn([
            new DiscoveredLink('https://example.com/page-1', false),
            new DiscoveredLink('https://external.example.org', true),
        ]);

        $metadataExtractor = $this->createMock(PageMetadataExtractorInterface::class);
        $metadataExtractor->method('extract')->willReturn(new PageMetadata());

        $auditor = $this->createMock(PageAuditorInterface::class);
        $auditor->method('audit')->willReturn([new Issue(IssueType::MissingTitle, 'The page has no <title> element.')]);

        $httpClient = new MockHttpClient(static fn(): MockResponse => new MockResponse(
            '<html><body>...</body></html>',
            ['response_headers' => ['content-type' => 'text/html; charset=UTF-8']],
        ));

        $crawler = new SiteCrawler(
            $linkExtractor,
            $metadataExtractor,
            $auditor,
            new DuplicateContentAuditor(),
            $httpClient,
            defaultMaxDepth: 1,
        );

        $report = $crawler->crawl($startUrl);

        $this->assertSame(2, $report->totalChecked);
        $this->assertSame(2, $report->getIssuesCount());
        $this->assertSame(
            ['https://example.com', 'https://example.com/page-1'],
            array_map(static fn(PageAudit $page): string => $page->url, $report->pages),
        );
    }

    public function testCrawlStopsDiscoveringLinksPastMaxDepth(): void
    {
        $startUrl = 'https://example.com';

        $linkExtractor = $this->createMock(InternalLinkExtractorInterface::class);
        $linkExtractor->method('extract')->willReturnCallback(static function (string $html, string $sourceUrl): array {
            if ($sourceUrl === 'https://example.com') {
                return [new DiscoveredLink('https://example.com/page-1', false)];
            }

            return [new DiscoveredLink('https://example.com/page-2', false)];
        });

        $metadataExtractor = $this->createMock(PageMetadataExtractorInterface::class);
        $metadataExtractor->method('extract')->willReturn(new PageMetadata());

        $auditor = $this->createMock(PageAuditorInterface::class);
        $auditor->method('audit')->willReturn([]);

        $httpClient = new MockHttpClient(static fn(): MockResponse => new MockResponse('<html></html>'));

        $crawler = new SiteCrawler(
            $linkExtractor,
            $metadataExtractor,
            $auditor,
            new DuplicateContentAuditor(),
            $httpClient,
            defaultMaxDepth: 1,
        );

        $report = $crawler->crawl($startUrl);

        $urls = array_map(static fn(PageAudit $page): string => $page->url, $report->pages);

        $this->assertContains('https://example.com/page-1', $urls);
        $this->assertNotContains('https://example.com/page-2', $urls);
    }

    public function testCrawlSkipsNonHtmlResponses(): void
    {
        $startUrl = 'https://example.com';

        $linkExtractor = $this->createMock(InternalLinkExtractorInterface::class);
        $linkExtractor->method('extract')->willReturn([
            new DiscoveredLink('https://example.com/document.pdf', false),
        ]);

        $metadataExtractor = $this->createMock(PageMetadataExtractorInterface::class);
        $metadataExtractor->method('extract')->willReturn(new PageMetadata());

        $auditor = $this->createMock(PageAuditorInterface::class);
        $auditor->method('audit')->willReturn([]);

        $httpClient = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_ends_with($url, '.pdf')) {
                return new MockResponse('%PDF-1.4', ['response_headers' => ['content-type' => 'application/pdf']]);
            }

            return new MockResponse('<html></html>', ['response_headers' => ['content-type' => 'text/html']]);
        });

        $crawler = new SiteCrawler(
            $linkExtractor,
            $metadataExtractor,
            $auditor,
            new DuplicateContentAuditor(),
            $httpClient,
            defaultMaxDepth: 1,
        );

        $report = $crawler->crawl($startUrl);

        $this->assertSame(1, $report->totalChecked);
        $this->assertSame('https://example.com', $report->pages[0]->url);
    }

    public function testCrawlSkipsInternalLinksDisallowedByRobotsTxt(): void
    {
        $startUrl = 'https://example.com';

        $linkExtractor = $this->createMock(InternalLinkExtractorInterface::class);
        $linkExtractor->method('extract')->willReturn([
            new DiscoveredLink('https://example.com/allowed', false),
            new DiscoveredLink('https://example.com/blocked', false),
        ]);

        $metadataExtractor = $this->createMock(PageMetadataExtractorInterface::class);
        $metadataExtractor->method('extract')->willReturnCallback(static function (string $html) {
            if (str_contains($html, 'blocked')) {
                throw new LogicException('A robots.txt-disallowed URL must never be audited.');
            }

            return new PageMetadata();
        });

        $auditor = $this->createMock(PageAuditorInterface::class);
        $auditor->method('audit')->willReturn([]);

        $httpClient = new MockHttpClient(static function (string $method, string $url): MockResponse {
            $body = str_contains($url, 'blocked') ? '<html>blocked</html>' : '<html>ok</html>';

            return new MockResponse($body, ['response_headers' => ['content-type' => 'text/html']]);
        });

        $robotsTxtChecker = $this->createMock(RobotsTxtCheckerInterface::class);
        $robotsTxtChecker->method('isAllowed')->willReturnCallback(
            static fn(string $url) => !str_contains($url, '/blocked')
        );

        $crawler = new SiteCrawler(
            $linkExtractor,
            $metadataExtractor,
            $auditor,
            new DuplicateContentAuditor(),
            $httpClient,
            robotsTxtChecker: $robotsTxtChecker,
            defaultMaxDepth: 1,
        );

        $report = $crawler->crawl($startUrl);

        $this->assertSame(2, $report->totalChecked);
    }

    public function testCrawlSendsConfiguredUserAgent(): void
    {
        $startUrl = 'https://example.com';

        $linkExtractor = $this->createMock(InternalLinkExtractorInterface::class);
        $linkExtractor->method('extract')->willReturn([]);

        $metadataExtractor = $this->createMock(PageMetadataExtractorInterface::class);
        $metadataExtractor->method('extract')->willReturn(new PageMetadata());

        $auditor = $this->createMock(PageAuditorInterface::class);
        $auditor->method('audit')->willReturn([]);

        $seenUserAgent = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$seenUserAgent) {
            foreach ($options['headers'] as $header) {
                if (str_starts_with($header, 'User-Agent:')) {
                    $seenUserAgent = trim(substr($header, strlen('User-Agent:')));
                }
            }

            return new MockResponse('<html></html>');
        });

        $crawler = new SiteCrawler(
            $linkExtractor,
            $metadataExtractor,
            $auditor,
            new DuplicateContentAuditor(),
            $httpClient,
            userAgent: 'MyCustomBot/1.0',
        );

        $crawler->crawl($startUrl);

        $this->assertSame('MyCustomBot/1.0', $seenUserAgent);
    }

    public function testCrawlDeduplicatesPagesReachedViaRedirect(): void
    {
        $startUrl = 'https://example.com';

        $linkExtractor = $this->createMock(InternalLinkExtractorInterface::class);
        $linkExtractor->method('extract')->willReturn([
            new DiscoveredLink('https://example.com/fr', false),
        ]);

        $metadataExtractor = $this->createMock(PageMetadataExtractorInterface::class);
        $metadataExtractor->method('extract')->willReturn(new PageMetadata());

        $auditor = $this->createMock(PageAuditorInterface::class);
        $auditor->method('audit')->willReturn([]);

        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;

            return new MockResponse(
                '<html></html>',
                [
                    'response_headers' => ['content-type' => 'text/html'],
                    'url' => 'https://example.com/fr',
                    'redirect_count' => 1,
                ],
            );
        });

        $crawler = new SiteCrawler(
            $linkExtractor,
            $metadataExtractor,
            $auditor,
            new DuplicateContentAuditor(),
            $httpClient,
            defaultMaxDepth: 1,
        );

        $report = $crawler->crawl($startUrl);

        $this->assertCount(1, $requestedUrls);
        $this->assertSame(1, $report->totalChecked);
        $this->assertSame(
            ['https://example.com/fr'],
            array_map(static fn(PageAudit $page): string => $page->url, $report->pages),
        );
    }

    public function testCrawlExemptsTheAuditedHostFromThrottling(): void
    {
        $startUrl = 'https://example.com';

        $linkExtractor = $this->createMock(InternalLinkExtractorInterface::class);
        $linkExtractor->method('extract')->willReturn([]);

        $metadataExtractor = $this->createMock(PageMetadataExtractorInterface::class);
        $metadataExtractor->method('extract')->willReturn(new PageMetadata());

        $auditor = $this->createMock(PageAuditorInterface::class);
        $auditor->method('audit')->willReturn([]);

        $httpClient = new class(new MockHttpClient(new MockResponse('<html></html>')))
            implements HttpClientInterface, ThrottleExemptionInterface {
            /** @var list<array{0: ?string, 1: int}> */
            public array $hostDelayCalls = [];

            public function __construct(private HttpClientInterface $inner)
            {
            }

            public function setHostDelay(?string $host, int $delayMs = 0): void
            {
                $this->hostDelayCalls[] = [$host, $delayMs];
            }

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return $this->inner->request($method, $url, $options);
            }

            public function stream(
                ResponseInterface|iterable $responses,
                ?float $timeout = null
            ): ResponseStreamInterface {
                return $this->inner->stream($responses, $timeout);
            }

            public function withOptions(array $options): static
            {
                $clone = clone $this;
                $clone->inner = $this->inner->withOptions($options);

                return $clone;
            }
        };

        $crawler = new SiteCrawler(
            $linkExtractor,
            $metadataExtractor,
            $auditor,
            new DuplicateContentAuditor(),
            $httpClient,
        );

        $crawler->crawl($startUrl);

        $this->assertSame([['example.com', 0], [null, 0]], $httpClient->hostDelayCalls);
    }
}

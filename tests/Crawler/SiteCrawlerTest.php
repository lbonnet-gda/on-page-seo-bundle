<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\Crawler;

use Lbonnet\OnPageSeoBundle\Auditor\PageAuditorInterface;
use Lbonnet\OnPageSeoBundle\Crawler\SiteCrawler;
use Lbonnet\OnPageSeoBundle\Extractor\InternalLinkExtractorInterface;
use Lbonnet\OnPageSeoBundle\Extractor\PageMetadataExtractorInterface;
use Lbonnet\OnPageSeoBundle\Model\DiscoveredLink;
use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

        $crawler = new SiteCrawler($linkExtractor, $metadataExtractor, $auditor, $httpClient, defaultMaxDepth: 1);

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

        $crawler = new SiteCrawler($linkExtractor, $metadataExtractor, $auditor, $httpClient, defaultMaxDepth: 1);

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

        $crawler = new SiteCrawler($linkExtractor, $metadataExtractor, $auditor, $httpClient, defaultMaxDepth: 1);

        $report = $crawler->crawl($startUrl);

        $this->assertSame(1, $report->totalChecked);
        $this->assertSame('https://example.com', $report->pages[0]->url);
    }
}

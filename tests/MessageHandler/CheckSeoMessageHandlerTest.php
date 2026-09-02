<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\MessageHandler;

use Lbonnet\OnPageSeoBundle\Crawler\CrawlerInterface;
use Lbonnet\OnPageSeoBundle\Message\CheckSeoMessage;
use Lbonnet\OnPageSeoBundle\MessageHandler\CheckSeoMessageHandler;
use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CheckSeoMessageHandlerTest extends TestCase
{
    public function testLogsAnErrorAndSkipsTheCrawlWhenNoUrlIsAvailable(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $crawler->expects($this->never())->method('crawl');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $handler = new CheckSeoMessageHandler(crawler: $crawler, defaultBaseUrl: null, logger: $logger);

        $handler(new CheckSeoMessage());
    }

    public function testCrawlsTheUrlFromTheMessageWhenProvided(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $crawler->expects($this->once())
            ->method('crawl')
            ->with('https://example.com/blog', 2, ['#/admin#'])
            ->willReturn(
                new SeoAuditReport(startUrl: 'https://example.com/blog', pages: [], totalChecked: 0, totalDuration: 0)
            );

        $handler = new CheckSeoMessageHandler(crawler: $crawler, defaultBaseUrl: 'https://default.example.com');

        $handler(
            new CheckSeoMessage(
                startUrl: 'https://example.com/blog',
                maxDepth: 2,
                excludePatterns: ['#/admin#'],
            )
        );
    }

    public function testFallsBackToTheConfiguredDefaultBaseUrlWhenTheMessageHasNone(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $crawler->expects($this->once())
            ->method('crawl')
            ->with('https://default.example.com', null, [])
            ->willReturn(
                new SeoAuditReport(startUrl: 'https://default.example.com', pages: [], totalChecked: 0, totalDuration: 0
                )
            );

        $handler = new CheckSeoMessageHandler(crawler: $crawler, defaultBaseUrl: 'https://default.example.com');

        $handler(new CheckSeoMessage());
    }
}

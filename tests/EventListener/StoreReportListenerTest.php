<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\EventListener;

use Lbonnet\OnPageSeoBundle\Event\CrawlCompletedEvent;
use Lbonnet\OnPageSeoBundle\EventListener\StoreReportListener;
use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use Lbonnet\OnPageSeoBundle\Storage\ReportStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class StoreReportListenerTest extends TestCase
{
    public function testDoesNothingWhenNoStorageIsConfigured(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $logger->expects($this->never())->method('error');

        $listener = new StoreReportListener(storage: null, logger: $logger);

        $listener(
            new CrawlCompletedEvent(
                new SeoAuditReport(startUrl: 'https://example.com', pages: [], totalChecked: 0, totalDuration: 0)
            )
        );
    }

    public function testLogsTheSavedLocationOnSuccess(): void
    {
        $storage = $this->createMock(ReportStorageInterface::class);
        $storage->method('save')->willReturn('/var/on_page_seo/report-123.json');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('/var/on_page_seo/report-123.json'));

        $listener = new StoreReportListener(storage: $storage, logger: $logger);

        $listener(
            new CrawlCompletedEvent(
                new SeoAuditReport(startUrl: 'https://example.com', pages: [], totalChecked: 0, totalDuration: 0)
            )
        );
    }

    public function testLogsAndSwallowsTheErrorWhenStorageThrows(): void
    {
        $storage = $this->createMock(ReportStorageInterface::class);
        $storage->method('save')->willThrowException(new RuntimeException('Disk full'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Disk full'));

        $listener = new StoreReportListener(storage: $storage, logger: $logger);

        $listener(
            new CrawlCompletedEvent(
                new SeoAuditReport(startUrl: 'https://example.com', pages: [], totalChecked: 0, totalDuration: 0)
            )
        );
    }
}

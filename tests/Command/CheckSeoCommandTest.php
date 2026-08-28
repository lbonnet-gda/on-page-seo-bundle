<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\Command;

use Lbonnet\OnPageSeoBundle\Command\CheckSeoCommand;
use Lbonnet\OnPageSeoBundle\Crawler\CrawlerInterface;
use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;
use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use Lbonnet\OnPageSeoBundle\Storage\ReportStorageInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckSeoCommandTest extends TestCase
{
    public function testExecuteFailsWhenNoUrlProvided(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $command = new CheckSeoCommand($crawler);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('No URL provided', $tester->getDisplay());
    }

    public function testExecuteSuccessWhenNoIssuesFound(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $crawler->method('crawl')->willReturn(
            new SeoAuditReport('https://example.com', [
                new PageAudit(
                    'https://example.com',
                    new PageMetadata(title: 'Home', metaDescription: 'Desc.', headings: ['Home']),
                    []
                ),
            ], 1, 0.12)
        );

        $command = new CheckSeoCommand($crawler, defaultBaseUrl: 'https://example.com');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('All clear!', $tester->getDisplay());
    }

    public function testExecuteFailsWhenIssuesFound(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $crawler->method('crawl')->willReturn(
            new SeoAuditReport(
                startUrl: 'https://example.com',
                pages: [
                    new PageAudit(
                        url: 'https://example.com',
                        metadata: new PageMetadata(),
                        issues: [new Issue(IssueType::MissingTitle, 'The page has no <title> element.')],
                    ),
                ],
                totalChecked: 1,
                totalDuration: 0.15,
            )
        );

        $command = new CheckSeoCommand($crawler, defaultBaseUrl: 'https://example.com');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('https://example.com', $tester->getDisplay());
        $this->assertStringContainsString('missing_title', $tester->getDisplay());
    }

    public function testExecutePersistsReportWhenStorageConfigured(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $crawler->method('crawl')->willReturn(
            new SeoAuditReport('https://example.com', [], 1, 0.12)
        );

        $storage = $this->createMock(ReportStorageInterface::class);
        $storage->method('save')->willReturn('/var/on_page_seo/report-123.json');

        $command = new CheckSeoCommand($crawler, $storage, 'https://example.com');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertStringContainsString('/var/on_page_seo/report-123.json', $tester->getDisplay());
    }

    public function testExecuteWarnsButSucceedsWhenStorageFails(): void
    {
        $crawler = $this->createMock(CrawlerInterface::class);
        $crawler->method('crawl')->willReturn(
            new SeoAuditReport('https://example.com', [], 1, 0.12)
        );

        $storage = $this->createMock(ReportStorageInterface::class);
        $storage->method('save')->willThrowException(new RuntimeException('Disk full'));

        $command = new CheckSeoCommand($crawler, $storage, 'https://example.com');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Failed to save report: Disk full', $tester->getDisplay());
    }
}

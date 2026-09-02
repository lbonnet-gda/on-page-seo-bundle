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
use PHPUnit\Framework\TestCase;
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
}

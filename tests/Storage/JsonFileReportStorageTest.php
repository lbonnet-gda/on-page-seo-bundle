<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\Storage;

use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;
use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use Lbonnet\OnPageSeoBundle\Storage\JsonFileReportStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonFileReportStorageTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/on_page_seo_tests_'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir.'/*') ?: []);
            rmdir($this->tempDir);
        } elseif (is_file($this->tempDir)) {
            unlink($this->tempDir);
        }
    }

    public function testSaveCreatesValidJsonReport(): void
    {
        $storage = new JsonFileReportStorage($this->tempDir);

        $report = new SeoAuditReport(
            startUrl: 'https://example.com',
            pages: [
                new PageAudit(
                    url: 'https://example.com',
                    metadata: new PageMetadata(title: 'Home', metaDescription: 'Desc.'),
                    issues: [new Issue(IssueType::MissingH1, 'The page has no <h1> heading.')],
                ),
            ],
            totalChecked: 1,
            totalDuration: 0.27,
        );

        $savedPath = $storage->save($report);

        $this->assertFileExists($savedPath);

        $decoded = json_decode((string)file_get_contents($savedPath), true);
        $this->assertSame('https://example.com', $decoded['startUrl']);
        $this->assertSame(1, $decoded['issuesCount']);
        $this->assertSame('Home', $decoded['pages'][0]['title']);
        $this->assertSame('missing_h1', $decoded['pages'][0]['issues'][0]['type']);
    }

    public function testSaveCreatesStorageDirectoryWhenMissing(): void
    {
        $this->assertDirectoryDoesNotExist($this->tempDir);

        $storage = new JsonFileReportStorage($this->tempDir);
        $storage->save(new SeoAuditReport(startUrl: 'https://example.com'));

        $this->assertDirectoryExists($this->tempDir);
    }

    public function testSaveWithNoPagesProducesEmptyReport(): void
    {
        $storage = new JsonFileReportStorage($this->tempDir);

        $savedPath = $storage->save(
            new SeoAuditReport(
                startUrl: 'https://example.com',
                totalChecked: 0,
                totalDuration: 0.1,
            )
        );

        $decoded = json_decode((string)file_get_contents($savedPath), true);

        $this->assertSame(0, $decoded['issuesCount']);
        $this->assertSame([], $decoded['pages']);
    }

    public function testSaveForDifferentStartUrlsProducesDistinctFiles(): void
    {
        $storage = new JsonFileReportStorage($this->tempDir);

        $firstPath = $storage->save(new SeoAuditReport(startUrl: 'https://example.com'));
        $secondPath = $storage->save(new SeoAuditReport(startUrl: 'https://another-example.com'));

        $this->assertNotSame($firstPath, $secondPath);
        $this->assertFileExists($firstPath);
        $this->assertFileExists($secondPath);
    }

    public function testConsecutiveSavesForSameStartUrlDoNotCollide(): void
    {
        $storage = new JsonFileReportStorage($this->tempDir);
        $report = new SeoAuditReport(startUrl: 'https://example.com');

        $firstPath = $storage->save($report);
        $secondPath = $storage->save($report);

        $this->assertNotSame(
            $firstPath,
            $secondPath,
            'Two saves within the same second must not overwrite each other.'
        );
        $this->assertFileExists($firstPath);
        $this->assertFileExists($secondPath);
    }

    public function testSaveDoesNotRotateWhenMaxReportsIsZero(): void
    {
        $storage = new JsonFileReportStorage($this->tempDir, maxReports: 0);
        $report = new SeoAuditReport(startUrl: 'https://example.com');

        for ($i = 0; $i < 5; $i++) {
            $storage->save($report);
        }

        $this->assertCount(5, glob($this->tempDir.'/*.json') ?: []);
    }

    public function testSaveRotatesOldestReportsPastTheLimit(): void
    {
        $storage = new JsonFileReportStorage($this->tempDir, maxReports: 3);
        $report = new SeoAuditReport(startUrl: 'https://example.com');

        $paths = [];
        for ($i = 0; $i < 5; $i++) {
            $paths[] = $storage->save($report);
            usleep(1_100_000);
        }

        $remaining = glob($this->tempDir.'/*.json') ?: [];
        $this->assertCount(3, $remaining);

        foreach (array_slice($paths, 0, 2) as $deleted) {
            $this->assertFileDoesNotExist($deleted);
        }
        foreach (array_slice($paths, 2) as $kept) {
            $this->assertFileExists($kept);
        }
    }

    public function testSaveThrowsWhenStorageDirectoryCannotBeCreated(): void
    {
        file_put_contents($this->tempDir, 'not a directory');

        $storage = new JsonFileReportStorage($this->tempDir);

        $this->expectException(RuntimeException::class);

        $storage->save(new SeoAuditReport(startUrl: 'https://example.com'));
    }
}

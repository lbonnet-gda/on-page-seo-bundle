<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Storage;

use DateTimeInterface;
use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;
use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use RuntimeException;

final class JsonFileReportStorage implements ReportStorageInterface
{
    public function __construct(
        private readonly string $storageDirectory,
        private readonly int $maxReports = 0,
    ) {
    }

    public function save(SeoAuditReport $report): string
    {
        if (
            !is_dir($this->storageDirectory)
            && !@mkdir($this->storageDirectory, 0775, true)
            && !is_dir($this->storageDirectory)
        ) {
            throw new RuntimeException(
                sprintf('Could not create report storage directory "%s".', $this->storageDirectory)
            );
        }

        $filename = sprintf(
            'report-%s-%s-%s.json',
            date('Y-m-d_H-i-s'),
            self::hash($report->startUrl),
            uniqid('', true)
        );

        $filePath = rtrim($this->storageDirectory, '/').'/'.$filename;

        $data = [
            'startUrl' => $report->startUrl,
            'createdAt' => date(DateTimeInterface::ATOM),
            'totalChecked' => $report->totalChecked,
            'totalDuration' => $report->totalDuration,
            'issuesCount' => $report->getIssuesCount(),
            'pages' => array_map(static fn(PageAudit $page): array => [
                'url' => $page->url,
                'title' => $page->metadata->title,
                'metaDescription' => $page->metadata->metaDescription,
                'issues' => array_map(static fn(Issue $issue): array => [
                    'type' => $issue->type->value,
                    'message' => $issue->message,
                ], $page->issues),
            ], $report->pages),
        ];

        $written = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($written === false) {
            throw new RuntimeException(sprintf('Could not write report file "%s".', $filePath));
        }

        $this->rotate($report->startUrl);

        return $filePath;
    }

    private function rotate(string $startUrl): void
    {
        if ($this->maxReports <= 0) {
            return;
        }

        $files = glob(
            sprintf(
                '%s/report-*-%s-*.json',
                rtrim($this->storageDirectory, '/'),
                self::hash($startUrl)
            )
        ) ?: [];

        sort($files);

        $excess = count($files) - $this->maxReports;
        for ($i = 0; $i < $excess; $i++) {
            @unlink($files[$i]);
        }
    }

    private static function hash(string $startUrl): string
    {
        return substr(md5($startUrl), 0, 8);
    }
}

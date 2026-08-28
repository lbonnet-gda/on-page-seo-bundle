<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Auditor;

use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\IssueType;
use Lbonnet\OnPageSeoBundle\Model\PageAudit;

final class DuplicateContentAuditor implements DuplicateContentAuditorInterface
{
    public function audit(array $pages): array
    {
        $duplicateTitleCounts = $this->countDuplicates(
            $pages,
            static fn(PageAudit $page): ?string => $page->metadata->title,
        );
        $duplicateDescriptionCounts = $this->countDuplicates(
            $pages,
            static fn(PageAudit $page): ?string => $page->metadata->metaDescription,
        );

        return array_map(
            function (PageAudit $page) use ($duplicateTitleCounts, $duplicateDescriptionCounts): PageAudit {
                $extraIssues = [];

                if (isset($duplicateTitleCounts[$page->url])) {
                    $extraIssues[] = new Issue(
                        IssueType::DuplicateTitle,
                        sprintf(
                            'The title "%s" is shared with %d other page(s).',
                            $page->metadata->title,
                            $duplicateTitleCounts[$page->url],
                        ),
                    );
                }

                if (isset($duplicateDescriptionCounts[$page->url])) {
                    $extraIssues[] = new Issue(
                        IssueType::DuplicateDescription,
                        sprintf(
                            'The meta description is shared with %d other page(s).',
                            $duplicateDescriptionCounts[$page->url]
                        ),
                    );
                }

                if ($extraIssues === []) {
                    return $page;
                }

                return new PageAudit($page->url, $page->metadata, [...$page->issues, ...$extraIssues]);
            },
            $pages,
        );
    }

    /**
     * @param list<PageAudit> $pages
     * @param callable(PageAudit): ?string $valueExtractor
     *
     * @return array<string, int> page URL => number of OTHER pages sharing the same value
     */
    private function countDuplicates(array $pages, callable $valueExtractor): array
    {
        $urlsByValue = [];

        foreach ($pages as $page) {
            $value = $valueExtractor($page);

            if ($value === null) {
                continue;
            }

            $urlsByValue[$value][] = $page->url;
        }

        $counts = [];

        foreach ($urlsByValue as $urls) {
            if (count($urls) < 2) {
                continue;
            }

            foreach ($urls as $url) {
                $counts[$url] = count($urls) - 1;
            }
        }

        return $counts;
    }
}

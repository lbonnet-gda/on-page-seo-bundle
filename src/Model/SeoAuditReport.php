<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Model;

final class SeoAuditReport
{
    /**
     * @param list<PageAudit> $pages
     */
    public function __construct(
        public readonly string $startUrl,
        public readonly array $pages = [],
        public readonly int $totalChecked = 0,
        public readonly float $totalDuration = 0.0,
    ) {
    }

    public function getIssuesCount(): int
    {
        return array_sum(
            array_map(
                static fn(PageAudit $page): int => count($page->issues),
                $this->pages,
            )
        );
    }

    public function hasIssues(): bool
    {
        return $this->getIssuesCount() > 0;
    }
}

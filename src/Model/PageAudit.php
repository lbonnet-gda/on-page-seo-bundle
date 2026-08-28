<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Model;

final class PageAudit
{
    /**
     * @param list<Issue> $issues
     */
    public function __construct(
        public readonly string $url,
        public readonly PageMetadata $metadata,
        public readonly array $issues = [],
    ) {
    }

    public function hasIssues(): bool
    {
        return count($this->issues) > 0;
    }
}

<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Auditor;

use Lbonnet\OnPageSeoBundle\Model\PageAudit;

interface DuplicateContentAuditorInterface
{
    /**
     * Flags pages that share the same title or meta-description with at least one other page.
     *
     * @param list<PageAudit> $pages
     *
     * @return list<PageAudit>
     */
    public function audit(array $pages): array;
}

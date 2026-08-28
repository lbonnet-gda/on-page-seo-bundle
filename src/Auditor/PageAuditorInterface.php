<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Auditor;

use Lbonnet\OnPageSeoBundle\Model\Issue;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;

interface PageAuditorInterface
{
    /**
     * Audits page metadata against configured SEO rules.
     *
     * @return list<Issue>
     */
    public function audit(PageMetadata $metadata): array;
}

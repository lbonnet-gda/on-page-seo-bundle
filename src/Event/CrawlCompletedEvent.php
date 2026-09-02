<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Event;

use Lbonnet\OnPageSeoBundle\Model\SeoAuditReport;
use Symfony\Contracts\EventDispatcher\Event;

final class CrawlCompletedEvent extends Event
{
    public function __construct(
        public readonly SeoAuditReport $report,
    ) {
    }
}

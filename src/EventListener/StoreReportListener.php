<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\EventListener;

use Lbonnet\OnPageSeoBundle\Event\CrawlCompletedEvent;
use Lbonnet\OnPageSeoBundle\Storage\ReportStorageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Throwable;

// High priority: the audit trail must be persisted before any other CrawlCompletedEvent
// listener (e.g., a user's notification listener) runs and potentially throws.
#[AsEventListener(priority: 100)]
final class StoreReportListener
{
    public function __construct(
        private readonly ?ReportStorageInterface $storage = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(CrawlCompletedEvent $event): void
    {
        if ($this->storage === null) {
            return;
        }

        try {
            $location = $this->storage->save($event->report);
            $this->logger->info(sprintf('[OnPageSeo] Report saved to %s', $location));
        } catch (Throwable $e) {
            $this->logger->error(sprintf('[OnPageSeo] Failed to save report: %s', $e->getMessage()));
        }
    }
}

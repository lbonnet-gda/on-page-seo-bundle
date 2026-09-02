<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\MessageHandler;

use Lbonnet\OnPageSeoBundle\Crawler\CrawlerInterface;
use Lbonnet\OnPageSeoBundle\Message\CheckSeoMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckSeoMessageHandler
{
    public function __construct(
        private readonly CrawlerInterface $crawler,
        private readonly ?string $defaultBaseUrl = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(CheckSeoMessage $message): void
    {
        $startUrl = $message->startUrl ?? $this->defaultBaseUrl;

        if ($startUrl === null || trim($startUrl) === '') {
            $this->logger->error('[OnPageSeo] No base URL configured or provided in CheckSeoMessage.');

            return;
        }

        $this->logger->info(sprintf('[OnPageSeo] Async crawl starting on: %s', $startUrl));

        $this->crawler->crawl(
            startUrl: $startUrl,
            maxDepth: $message->maxDepth,
            excludePatterns: $message->excludePatterns,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Message;

final class CheckSeoMessage
{
    /**
     * @param list<string> $excludePatterns
     */
    public function __construct(
        public readonly ?string $startUrl = null,
        public readonly ?int $maxDepth = null,
        public readonly array $excludePatterns = [],
    ) {
    }
}

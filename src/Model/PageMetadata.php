<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Model;

final class PageMetadata
{
    /**
     * @param list<string> $headings
     * @param list<string> $imagesMissingAlt
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $metaDescription = null,
        public readonly array $headings = [],
        public readonly array $imagesMissingAlt = [],
    ) {
    }
}

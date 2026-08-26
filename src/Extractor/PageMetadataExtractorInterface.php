<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Extractor;

use Lbonnet\OnPageSeoBundle\Model\PageMetadata;

interface PageMetadataExtractorInterface
{
    /**
     * @param string $html The HTML content of the page
     */
    public function extract(string $html): PageMetadata;
}

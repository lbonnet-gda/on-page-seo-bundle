<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Extractor;

use DOMElement;
use Lbonnet\OnPageSeoBundle\Model\PageMetadata;
use Symfony\Component\DomCrawler\Crawler;

final class HtmlPageMetadataExtractor implements PageMetadataExtractorInterface
{
    public function extract(string $html): PageMetadata
    {
        if (trim($html) === '') {
            return new PageMetadata();
        }

        $crawler = new Crawler($html);

        return new PageMetadata(
            title: $this->extractTitle($crawler),
            metaDescription: $this->extractMetaDescription($crawler),
            headings: $this->extractHeadings($crawler),
            imagesMissingAlt: $this->extractImagesMissingAlt($crawler),
        );
    }

    private function extractTitle(Crawler $crawler): ?string
    {
        $titleNode = $crawler->filter('title');

        if ($titleNode->count() === 0) {
            return null;
        }

        $title = trim($titleNode->first()->text());

        return $title !== '' ? $title : null;
    }

    private function extractMetaDescription(Crawler $crawler): ?string
    {
        foreach ($crawler->filter('meta[name]') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            if (strcasecmp($element->getAttribute('name'), 'description') !== 0) {
                continue;
            }

            $content = trim($element->getAttribute('content'));

            return $content !== '' ? $content : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractHeadings(Crawler $crawler): array
    {
        $headings = [];

        foreach ($crawler->filter('h1') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            $headings[] = trim($element->textContent);
        }

        return $headings;
    }

    /**
     * @return list<string>
     */
    private function extractImagesMissingAlt(Crawler $crawler): array
    {
        $missing = [];

        foreach ($crawler->filter('img') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            if ($element->hasAttribute('alt')) {
                continue;
            }

            $missing[] = trim($element->getAttribute('src'));
        }

        return $missing;
    }
}

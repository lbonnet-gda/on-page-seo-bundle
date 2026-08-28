<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Extractor;

use DOMElement;
use Lbonnet\OnPageSeoBundle\Model\DiscoveredLink;
use Symfony\Component\DomCrawler\Crawler;

final class HtmlInternalLinkExtractor implements InternalLinkExtractorInterface
{
    private const IGNORED_SCHEMES = ['mailto:', 'tel:', 'javascript:', 'data:', 'sms:', 'whatsapp:'];

    public function extract(string $html, string $sourceUrl, array $excludePatterns = []): array
    {
        if (trim($html) === '') {
            return [];
        }

        $crawler = new Crawler($html, $sourceUrl);
        $sourceHost = parse_url($sourceUrl, PHP_URL_HOST);

        $discoveredLinks = [];
        $seenUrls = [];

        foreach ($crawler->filter('a[href]') as $element) {
            if (!$element instanceof DOMElement) {
                continue;
            }

            $rawHref = trim($element->getAttribute('href'));

            if ($this->shouldIgnore($rawHref)) {
                continue;
            }

            $cleanUrl = $this->stripFragment($this->resolveUrl($sourceUrl, $rawHref));

            if ($cleanUrl === '' || isset($seenUrls[$cleanUrl])) {
                continue;
            }

            if ($this->isExcluded($cleanUrl, $excludePatterns)) {
                continue;
            }

            $targetHost = parse_url($cleanUrl, PHP_URL_HOST);
            $isExternal =
                is_string($targetHost) && is_string($sourceHost)
                && (strcasecmp($targetHost, $sourceHost) !== 0);

            $seenUrls[$cleanUrl] = true;
            $discoveredLinks[] = new DiscoveredLink(url: $cleanUrl, isExternal: $isExternal);
        }

        return $discoveredLinks;
    }

    private function shouldIgnore(string $href): bool
    {
        if ($href === '' || $href === '#') {
            return true;
        }

        if (str_starts_with($href, '#')) {
            return true;
        }

        $lower = strtolower($href);
        foreach (self::IGNORED_SCHEMES as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return true;
            }
        }

        return false;
    }

    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        if (preg_match('#^https?://#i', $relativeUrl) === 1) {
            return $relativeUrl;
        }

        $parsedBase = parse_url($baseUrl);
        if ($parsedBase === false) {
            return $relativeUrl;
        }

        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';
        $port = isset($parsedBase['port']) ? ':'.$parsedBase['port'] : '';
        $basePath = $parsedBase['path'] ?? '/';

        if (str_starts_with($relativeUrl, '//')) {
            return $scheme.':'.$relativeUrl;
        }

        if (str_starts_with($relativeUrl, '/')) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $relativeUrl);
        }

        $dir = preg_replace('#/[^/]*$#', '', $basePath);
        $combinedPath = rtrim((string)$dir, '/').'/'.$relativeUrl;

        $segments = explode('/', $combinedPath);
        $resolvedSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($resolvedSegments);
            } else {
                $resolvedSegments[] = $segment;
            }
        }

        return sprintf('%s://%s%s/%s', $scheme, $host, $port, implode('/', $resolvedSegments));
    }

    private function stripFragment(string $url): string
    {
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            return substr($url, 0, $hashPos);
        }

        return $url;
    }

    /**
     * @param list<string> $patterns
     */
    private function isExcluded(string $url, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $url) === 1) {
                return true;
            }
        }

        return false;
    }
}

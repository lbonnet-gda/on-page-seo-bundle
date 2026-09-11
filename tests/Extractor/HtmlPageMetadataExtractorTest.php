<?php

declare(strict_types=1);

namespace Lbonnet\OnPageSeoBundle\Tests\Extractor;

use Lbonnet\OnPageSeoBundle\Extractor\HtmlPageMetadataExtractor;
use PHPUnit\Framework\TestCase;

final class HtmlPageMetadataExtractorTest extends TestCase
{
    private HtmlPageMetadataExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new HtmlPageMetadataExtractor();
    }

    public function testExtractsTitleAndMetaDescription(): void
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
            <head>
                <title>  Home Page  </title>
                <meta name="Description" content="A short description.">
            </head>
            <body></body>
        </html>
        HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertSame('Home Page', $metadata->title);
        $this->assertSame('A short description.', $metadata->metaDescription);
    }

    public function testMissingTitleAndDescriptionAreNull(): void
    {
        $html = '<html><head></head><body><h1>Content</h1></body></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->title);
        $this->assertNull($metadata->metaDescription);
    }

    public function testEmptyTitleAndDescriptionAreTreatedAsMissing(): void
    {
        $html = '<html><head><title>   </title><meta name="description" content="  "></head></html>';

        $metadata = $this->extractor->extract($html);

        $this->assertNull($metadata->title);
        $this->assertNull($metadata->metaDescription);
    }

    public function testExtractsAllH1Headings(): void
    {
        $html = '<h1>First</h1><p>text</p><h1> Second </h1>';

        $metadata = $this->extractor->extract($html);

        $this->assertSame(['First', 'Second'], $metadata->headings);
    }

    public function testFlagsImagesMissingAltAttribute(): void
    {
        $html = <<<HTML
        <img src="/logo.png" alt="Logo">
        <img src="/decorative.png" alt="">
        <img src="/broken.png">
        HTML;

        $metadata = $this->extractor->extract($html);

        $this->assertSame(['/broken.png'], $metadata->imagesMissingAlt);
    }

    /**
     * @dataProvider templateDocumentProvider
     */
    public function testIgnoresContentInsideTemplate(string $html): void
    {
        $metadata = $this->extractor->extract($html);

        $this->assertSame('Real title', $metadata->title);
        $this->assertSame('Real description', $metadata->metaDescription);
        $this->assertSame(['Visible'], $metadata->headings);
        $this->assertSame(['/visible.png'], $metadata->imagesMissingAlt);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function templateDocumentProvider(): iterable
    {
        $head = '<template><title>Template title</title><meta name="description" content="Template description">'
            .'</template><title>Real title</title><meta name="description" content="Real description">';
        $body = '<template><h1>Hidden</h1><img src="/hidden.png"></template><h1>Visible</h1><img src="/visible.png">';

        // DomCrawler picks its parser from the doctype, so both are covered.
        yield 'HTML5 document' => ["<!DOCTYPE html><html><head>{$head}</head><body>{$body}</body></html>"];
        yield 'document without doctype' => ["<html><head>{$head}</head><body>{$body}</body></html>"];
    }

    public function testEmptyHtmlReturnsEmptyMetadata(): void
    {
        $metadata = $this->extractor->extract('');

        $this->assertNull($metadata->title);
        $this->assertNull($metadata->metaDescription);
        $this->assertSame([], $metadata->headings);
        $this->assertSame([], $metadata->imagesMissingAlt);
    }
}

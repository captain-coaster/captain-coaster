<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\SitemapService;
use App\Service\SitemapWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SitemapWriterTest extends TestCase
{
    private SitemapService&MockObject $sitemapService;
    private string $directory;

    protected function setUp(): void
    {
        $this->sitemapService = $this->createMock(SitemapService::class);
        $this->directory = sys_get_temp_dir().'/sitemap-writer-test-'.uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        @rmdir($this->directory);
    }

    private function writer(): SitemapWriter
    {
        return new SitemapWriter($this->sitemapService, $this->directory);
    }

    private function load(string $file): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($this->directory.'/'.$file), $file.' must be well-formed XML');

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');
        $xpath->registerNamespace('image', 'http://www.google.com/schemas/sitemap-image/1.1');

        return $xpath;
    }

    public function testPagesFileListsLocAlternatesAndLastmod(): void
    {
        $alternates = ['en' => 'https://captaincoaster.com/en/a?x=1&y=2', 'fr' => 'https://captaincoaster.com/fr/a?x=1&y=2'];
        $this->sitemapService->method('getUrlsForPages')->willReturn([
            ['loc' => $alternates['en'], 'alternates' => $alternates, 'lastmod' => '2026-10-01T18:30:00+02:00'],
            ['loc' => $alternates['fr'], 'alternates' => $alternates],
        ]);

        self::assertSame(2, $this->writer()->writePages());

        $xpath = $this->load('sitemap.xml');
        self::assertSame(2, (int) $xpath->evaluate('count(/s:urlset/s:url)'));
        // Escaped in the file, intact once parsed.
        self::assertSame($alternates['en'], $xpath->evaluate('string(/s:urlset/s:url[1]/s:loc)'));
        self::assertSame($alternates['fr'], $xpath->evaluate('string(/s:urlset/s:url[1]/xhtml:link[@hreflang="fr"][@rel="alternate"]/@href)'));
        self::assertSame($alternates['en'], $xpath->evaluate('string(/s:urlset/s:url[1]/xhtml:link[@hreflang="en"]/@href)'));
        self::assertSame('2026-10-01T18:30:00+02:00', $xpath->evaluate('string(/s:urlset/s:url[1]/s:lastmod)'));
        self::assertSame(0, (int) $xpath->evaluate('count(/s:urlset/s:url[2]/s:lastmod)'));
        self::assertSame(0, (int) $xpath->evaluate('count(//s:changefreq | //s:priority)'));
    }

    public function testImagesFileListsThePhotosOfEachPage(): void
    {
        $this->sitemapService->method('getUrlsForImages')->willReturn([
            ['loc' => 'https://captaincoaster.com/en/coasters/42/voltron', 'images' => ['https://pictures.example.com/i/1.jpg', 'https://pictures.example.com/i/2.jpg']],
            ['loc' => 'https://captaincoaster.com/en/coasters/43/taron', 'images' => ['https://pictures.example.com/i/3.jpg']],
        ]);

        self::assertSame(['pages' => 2, 'images' => 3], $this->writer()->writeImages());

        $xpath = $this->load('sitemap_image.xml');
        self::assertSame(2, (int) $xpath->evaluate('count(/s:urlset/s:url)'));
        self::assertSame(2, (int) $xpath->evaluate('count(/s:urlset/s:url[1]/image:image/image:loc)'));
        self::assertSame('https://pictures.example.com/i/3.jpg', $xpath->evaluate('string(/s:urlset/s:url[2]/image:image/image:loc)'));
        self::assertSame(0, (int) $xpath->evaluate('count(//image:title | //image:geo_location)'));
    }

    public function testTheFileIsReadableByTheWebServerAndNoTemporaryFileIsLeft(): void
    {
        $this->sitemapService->method('getUrlsForPages')->willReturn([['loc' => 'https://captaincoaster.com/en/', 'alternates' => []]]);

        $this->writer()->writePages();

        self::assertSame(0o644, fileperms($this->directory.'/sitemap.xml') & 0o777);
        self::assertSame(['sitemap.xml'], array_map('basename', glob($this->directory.'/*') ?: []));
    }

    public function testAnEmptyBuildKeepsThePreviousFile(): void
    {
        file_put_contents($this->directory.'/sitemap_image.xml', 'previous');
        $this->sitemapService->method('getUrlsForImages')->willReturn([]);

        try {
            $this->writer()->writeImages();
            self::fail('An empty sitemap must not replace the previous one.');
        } catch (\RuntimeException) {
            self::assertSame('previous', file_get_contents($this->directory.'/sitemap_image.xml'));
            self::assertFileDoesNotExist($this->directory.'/sitemap_image.xml.tmp');
        }
    }

    public function testAFailureMidwayKeepsThePreviousFile(): void
    {
        file_put_contents($this->directory.'/sitemap.xml', 'previous');
        $this->sitemapService->method('getUrlsForPages')->willReturnCallback(static function (): \Generator {
            yield ['loc' => 'https://captaincoaster.com/en/', 'alternates' => []];

            throw new \RuntimeException('database is down');
        });

        try {
            $this->writer()->writePages();
            self::fail('The error must surface.');
        } catch (\RuntimeException $e) {
            self::assertSame('database is down', $e->getMessage());
            self::assertSame('previous', file_get_contents($this->directory.'/sitemap.xml'));
            self::assertFileDoesNotExist($this->directory.'/sitemap.xml.tmp');
        }
    }

    public function testAnUnwritableDirectoryIsReported(): void
    {
        $writer = new SitemapWriter($this->sitemapService, $this->directory.'/missing');

        $this->expectException(\RuntimeException::class);

        $writer->writePages();
    }
}

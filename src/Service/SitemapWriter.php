<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes sitemap.xml and sitemap_image.xml as static files in the web root, where the web
 * server serves them without reaching PHP (run by `sitemap:update`, daily).
 *
 * Each file is streamed to a temporary file next to its target and renamed over it at the end:
 * a crawler never reads a half-written sitemap, and a failed or empty build leaves the previous
 * file in place.
 */
class SitemapWriter
{
    public const string PAGES_FILE = 'sitemap.xml';
    public const string IMAGES_FILE = 'sitemap_image.xml';

    /** A sitemap file holds at most 50,000 URLs; beyond that it must be split behind a sitemap index. */
    public const int MAX_URLS = 50000;

    private const string SITEMAP_NS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public function __construct(
        private readonly SitemapService $sitemapService,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $directory,
    ) {
    }

    /** @return int the number of URLs written */
    public function writePages(): int
    {
        return $this->write(
            self::PAGES_FILE,
            ['xmlns:xhtml' => 'http://www.w3.org/1999/xhtml'],
            $this->sitemapService->getUrlsForPages(),
            static function (\XMLWriter $xml, array $url): void {
                foreach ($url['alternates'] as $locale => $alternate) {
                    $xml->startElement('xhtml:link');
                    $xml->writeAttribute('rel', 'alternate');
                    $xml->writeAttribute('hreflang', $locale);
                    $xml->writeAttribute('href', $alternate);
                    $xml->endElement();
                }
                if (isset($url['lastmod'])) {
                    $xml->writeElement('lastmod', $url['lastmod']);
                }
            },
        );
    }

    /** @return array{pages: int, images: int} */
    public function writeImages(): array
    {
        $images = 0;
        $pages = $this->write(
            self::IMAGES_FILE,
            ['xmlns:image' => 'http://www.google.com/schemas/sitemap-image/1.1'],
            $this->sitemapService->getUrlsForImages(),
            static function (\XMLWriter $xml, array $url) use (&$images): void {
                foreach ($url['images'] as $image) {
                    $xml->startElement('image:image');
                    $xml->writeElement('image:loc', $image);
                    $xml->endElement();
                    ++$images;
                }
            },
        );

        return ['pages' => $pages, 'images' => $images];
    }

    /**
     * @template T of array{loc: string}
     *
     * @param array<string, string>         $namespaces    extra xmlns attributes of <urlset>
     * @param iterable<T>                   $urls
     * @param \Closure(\XMLWriter, T): void $writeChildren writes what follows <loc> in a <url>
     */
    private function write(string $file, array $namespaces, iterable $urls, \Closure $writeChildren): int
    {
        $target = $this->directory.'/'.$file;
        $temporary = $target.'.tmp';

        $xml = new \XMLWriter();
        if (!@$xml->openUri($temporary)) {
            throw new \RuntimeException(\sprintf('Cannot write "%s".', $temporary));
        }

        try {
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('urlset');
            $xml->writeAttribute('xmlns', self::SITEMAP_NS);
            foreach ($namespaces as $name => $uri) {
                $xml->writeAttribute($name, $uri);
            }
            $xml->text("\n");

            $count = 0;
            foreach ($urls as $url) {
                $xml->startElement('url');
                $xml->writeElement('loc', $url['loc']);
                $writeChildren($xml, $url);
                $xml->endElement();
                $xml->text("\n");

                if (0 === ++$count % 1000) {
                    $xml->flush();
                }
            }

            $xml->endElement();
            $xml->endDocument();
            $xml->flush();

            if (0 === $count) {
                throw new \RuntimeException(\sprintf('No URL to write in "%s", the previous file is kept.', $file));
            }
            if ($count > self::MAX_URLS) {
                throw new \RuntimeException(\sprintf('"%s" would hold %d URLs, above the limit of %d: split it behind a sitemap index. The previous file is kept.', $file, $count, self::MAX_URLS));
            }

            // Readable by the web server whatever the umask of the user running the command.
            chmod($temporary, 0o644);
            if (!rename($temporary, $target)) {
                throw new \RuntimeException(\sprintf('Cannot replace "%s".', $target));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return $count;
    }
}

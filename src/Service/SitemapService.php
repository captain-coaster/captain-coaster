<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\PictureRef;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lists the entries of sitemap.xml (pages) and sitemap_image.xml (photos); SitemapWriter turns
 * them into files.
 *
 * Follows Google's sitemap rules: every language version lists all versions including itself,
 * <lastmod> is the only optional tag kept (<changefreq> and <priority> are ignored), and the
 * image sitemap has one entry per page holding its photos (<image:loc> only: the title and
 * geo_location tags were removed from the format).
 */
class SitemapService
{
    /** Google reads at most this many <image:image> per <url>. */
    private const int MAX_IMAGES_PER_PAGE = 1000;

    /** @param array<string> $locales */
    public function __construct(
        private readonly CoasterRepository $coasterRepository,
        private readonly ImageRepository $imageRepository,
        private readonly UrlGeneratorInterface $router,
        private readonly PictureUrlSigner $pictureUrlSigner,
        private readonly array $locales,
    ) {
    }

    /** @return iterable<array{loc: string, alternates: array<string, string>, lastmod?: string}> */
    public function getUrlsForPages(): iterable
    {
        $coasters = $this->coasterRepository->findForSitemap();

        // Home: changes with every rating. 'Y-m-d H:i:s' strings sort chronologically.
        $lastRatings = array_filter(array_column($coasters, 'lastmod'));
        yield from $this->localizedUrls('default_index', [], [] !== $lastRatings ? new \DateTimeImmutable(max($lastRatings)) : null);

        // Ranking: recomputed once a month.
        yield from $this->localizedUrls('ranking_index', [], new \DateTimeImmutable('first day of this month midnight'));

        foreach ($coasters as $coaster) {
            if (null === $coaster['slug']) {
                continue;
            }

            yield from $this->localizedUrls(
                'show_coaster',
                ['id' => $coaster['id'], 'slug' => $coaster['slug']],
                null !== $coaster['lastmod'] ? new \DateTimeImmutable($coaster['lastmod']) : null,
            );
        }
    }

    /**
     * One entry per coaster page (English URL) with its published, watermarked photos.
     *
     * @return iterable<array{loc: string, images: list<string>}>
     */
    public function getUrlsForImages(): iterable
    {
        $coasterId = null;
        $loc = '';
        $images = [];

        // Rows come grouped by coaster.
        foreach ($this->imageRepository->findForSitemap() as $row) {
            if (null === $row['coasterSlug']) {
                continue;
            }

            if ($row['coasterId'] !== $coasterId) {
                if ([] !== $images) {
                    yield ['loc' => $loc, 'images' => $images];
                }
                $coasterId = $row['coasterId'];
                $loc = $this->router->generate(
                    'show_coaster',
                    ['id' => $coasterId, 'slug' => $row['coasterSlug'], '_locale' => 'en'],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                $images = [];
            }

            if (\count($images) < self::MAX_IMAGES_PER_PAGE) {
                // The lightbox derivative: whole photo, inside 1440x1440, real .jpg extension.
                $images[] = $this->pictureUrlSigner->signImage(
                    new PictureRef($row['id'], $row['filename'], $row['focalX'], $row['focalY'], $row['watermarked'], $row['coasterSlug']),
                    1440,
                    1440,
                    'jpg',
                );
            }
        }

        if ([] !== $images) {
            yield ['loc' => $loc, 'images' => $images];
        }
    }

    /**
     * One entry per locale, each listing every language version, itself included.
     *
     * @param array<string, mixed> $params
     *
     * @return list<array{loc: string, alternates: array<string, string>, lastmod?: string}>
     */
    private function localizedUrls(string $route, array $params, ?\DateTimeInterface $lastmod): array
    {
        $alternates = [];
        foreach ($this->locales as $locale) {
            $alternates[$locale] = $this->router->generate(
                $route,
                ['_locale' => $locale] + $params,
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        $urls = [];
        foreach ($alternates as $loc) {
            $url = ['loc' => $loc, 'alternates' => $alternates];
            if (null !== $lastmod) {
                $url['lastmod'] = $lastmod->format(\DateTimeInterface::W3C);
            }
            $urls[] = $url;
        }

        return $urls;
    }
}

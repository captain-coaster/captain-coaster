<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the entries of sitemap.xml (pages) and sitemap_image.xml (photos).
 *
 * Follows Google's sitemap rules: every language version lists all versions including itself,
 * <lastmod> is the only optional tag kept (<changefreq> and <priority> are ignored), and the
 * image sitemap has one entry per page holding its photos (<image:loc> only: the title and
 * geo_location tags were removed from the format).
 *
 * Errors are not caught here: an empty list would be cached and served as the sitemap.
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

    /** @return list<array{loc: string, alternates: array<string, string>, lastmod?: string}> */
    public function getUrlsForPages(): array
    {
        $coasters = $this->coasterRepository->findForSitemap();

        // Home: changes with every rating. 'Y-m-d H:i:s' strings sort chronologically.
        $lastRatings = array_filter(array_column($coasters, 'lastmod'));
        $urls = $this->localizedUrls('default_index', [], [] !== $lastRatings ? new \DateTimeImmutable(max($lastRatings)) : null);

        // Ranking: recomputed once a month.
        array_push($urls, ...$this->localizedUrls('ranking_index', [], new \DateTimeImmutable('first day of this month midnight')));

        foreach ($coasters as $coaster) {
            if (null === $coaster['slug']) {
                continue;
            }

            array_push($urls, ...$this->localizedUrls(
                'show_coaster',
                ['id' => $coaster['id'], 'slug' => $coaster['slug']],
                null !== $coaster['lastmod'] ? new \DateTimeImmutable($coaster['lastmod']) : null,
            ));
        }

        return $urls;
    }

    /**
     * One entry per coaster page (English URL) with its published, watermarked photos.
     *
     * @return list<array{loc: string, images: list<string>}>
     */
    public function getUrlsForImages(): array
    {
        $urls = [];

        foreach ($this->imageRepository->findForSitemap() as $image) {
            $coaster = $image->getCoaster();
            $coasterId = $coaster->getId();
            if (null === $coasterId || null === $coaster->getSlug()) {
                continue;
            }

            $urls[$coasterId] ??= [
                'loc' => $this->router->generate(
                    'show_coaster',
                    ['id' => $coasterId, 'slug' => $coaster->getSlug(), '_locale' => 'en'],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'images' => [],
            ];

            if (\count($urls[$coasterId]['images']) < self::MAX_IMAGES_PER_PAGE) {
                // The lightbox derivative: whole photo, inside 1440x1440, real .jpg extension.
                $urls[$coasterId]['images'][] = $this->pictureUrlSigner->signImage($image, 1440, 1440, 'jpg');
            }
        }

        return array_values($urls);
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

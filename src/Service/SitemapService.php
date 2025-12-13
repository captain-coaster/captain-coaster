<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\RiddenCoaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SitemapService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly array $locales,
    ) {
    }

    public function getUrlsForPages(): array
    {
        $urls = [];

        try {
            // Latest review
            $latestRating = $this->em->getRepository(RiddenCoaster::class)->findOneBy([], ['updatedAt' => 'desc']);
            $indexUpdateDate = $latestRating ? $latestRating->getUpdatedAt() : new \DateTime();

            // Index
            $indexUrls = $this->getUrlAndAlternates('default_index', [], $indexUpdateDate, 'daily', 1);
            $urls = array_merge($urls, $indexUrls);

            // Ranking
            $rankingUpdateDate = new \DateTime('first day of this month midnight');
            $rankingUrls = $this->getUrlAndAlternates('ranking_index', [], $rankingUpdateDate, 'monthly', 1);
            $urls = array_merge($urls, $rankingUrls);

            // Fiche Coasters
            $coasters = $this->em->getRepository(Coaster::class)->findAll();

            foreach ($coasters as $coaster) {
                $params = ['id' => $coaster->getId(), 'slug' => $coaster->getSlug()];
                $date = null;
                // Latest review
                $latestReview = $this->em->getRepository(RiddenCoaster::class)->findOneBy(['coaster' => $coaster], ['updatedAt' => 'desc']);
                if ($latestReview instanceof RiddenCoaster) {
                    $date = $latestReview->getUpdatedAt();
                }
                $coasterUrls = $this->getUrlAndAlternates('show_coaster', $params, $date, 'weekly', '0.8');
                $urls = array_merge($urls, $coasterUrls);
            }

            return $urls;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getUrlsForImages(): array
    {
        $urls = [];

        try {
            // Latest review
            $images = $this->em->getRepository(Image::class)->findBy(['watermarked' => true]);

            foreach ($images as $image) {
                $url = [];
                $url['loc'] = $this->router->generate(
                    'show_coaster',
                    [
                        'id' => $image->getCoaster()->getId(),
                        'slug' => $image->getCoaster()->getSlug(),
                        '_locale' => 'en',
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $imageXML = [];

                $imageXML['loc'] = \sprintf(
                    '%s://%s/%s/%s',
                    $this->router->getContext()->getScheme(),
                    $this->router->getContext()->getHost(),
                    'images/coasters',
                    $image->getFilename()
                );

                $imageXML['title'] = $image->getCoaster()->getName();
                $imageXML['geo_location'] = \sprintf(
                    '%s, %s',
                    $image->getCoaster()->getPark()->getName(),
                    $this->translator->trans($image->getCoaster()->getPark()->getCountry()->getName(), [], 'database', 'en')
                );

                $url['images'][] = $imageXML;

                $urls[] = $url;
            }

            return $urls;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getUrlAndAlternates(
        $route,
        array $params = [],
        ?\DateTimeInterface $lastmod = null,
        $changefreq = 'weekly',
        $priority = '0.5'
    ): array {
        $urls = [];

        foreach ($this->locales as $locale) {
            $url = [];

            $url['loc'] = $this->router->generate(
                $route,
                $this->buildRouteParams($params, $locale),
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $url['changefreq'] = $changefreq;
            $url['priority'] = $priority;

            if (null !== $lastmod) {
                $url['lastmod'] = $lastmod->format(\DateTime::W3C);
            }

            foreach ($this->locales as $alternateLocale) {
                if ($alternateLocale !== $locale) {
                    $url['alternate'][$alternateLocale] = $this->router->generate(
                        $route,
                        $this->buildRouteParams($params, $alternateLocale),
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );
                }
            }

            $urls[] = $url;
        }

        return $urls;
    }

    private function buildRouteParams(array $params, $locale): array
    {
        return array_merge(
            ['_locale' => $locale],
            $params
        );
    }
}

<?php

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class SitemapService
 * @package App\Service
 */
class SitemapService
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * SitemapService constructor.
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
        TranslatorInterface $translator,
        private readonly array $locales,
        private readonly \Unknown_Repository_Class $Repository
    ) {
        $this->translator = $translator;
    }

    /**
     * @return array
     */
    public function getUrlsForPages()
    {
        $urls = [];

        // Latest review
        $latestRating = $this->Repository
            ->findOneBy([], ['updatedAt' => 'desc'], 1);
        $indexUpdateDate = $latestRating->getUpdatedAt();

        // Index
        $indexUrls = $this->getUrlAndAlternates('bdd_index', [], $indexUpdateDate, 'daily', 1);
        $urls = array_merge($urls, $indexUrls);

        // Ranking
        $rankingUpdateDate = new \DateTime('first day of this month midnight');
        $rankingUrls = $this->getUrlAndAlternates('ranking_index', [], $rankingUpdateDate, 'monthly', 1);
        $urls = array_merge($urls, $rankingUrls);

        // Fiche Coasters
        $coasters = $this->em->getRepository(Coaster::class)->findAll();
        foreach ($coasters as $coaster) {
            $params = ['slug' => $coaster->getSlug()];
            $date = null;
            // Latest review
            $latestReview = $this->Repository
                ->findOneBy(['coaster' => $coaster], ['updatedAt' => 'desc'], 1);
            if ($latestReview instanceof RiddenCoaster) {
                $date = $latestReview->getUpdatedAt();
            }
            $coasterUrls = $this->getUrlAndAlternates('bdd_show_coaster', $params, $date, 'weekly', '0.8');
            $urls = array_merge($urls, $coasterUrls);
        }

        return $urls;
    }

    /**
     * @return array
     */
    public function getUrlsForImages()
    {
        $urls = [];

        // Latest review
        $images = $this->Repository
            ->findBy(['watermarked' => true]);

        foreach ($images as $image) {
            $url = [];
            $url['loc'] = $this->router->generate(
                'bdd_show_coaster',
                ['slug' => $image->getCoaster()->getSlug(), '_locale' => 'en'],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $imageXML = [];

            $imageXML['loc'] = sprintf(
                '%s://%s/%s/%s',
                $this->router->getContext()->getScheme(),
                $this->router->getContext()->getHost(),
                'images/coasters',
                $image->getPath()
            );

            $imageXML['title'] = $image->getCoaster()->getName();
            $imageXML['geo_location'] = sprintf(
                '%s, %s',
                $image->getCoaster()->getPark()->getName(),
                $this->translator->trans($image->getCoaster()->getPark()->getCountry()->getName(), [], 'database', 'en')
            );

            $url['images'][] = $imageXML;

            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * @param $route
     * @param \DateTime|null $lastmod
     * @param string $changefreq
     * @param string $priority
     * @return array
     */
    private function getUrlAndAlternates(
        $route,
        array $params = [],
        \DateTime $lastmod = null,
        $changefreq = 'weekly',
        $priority = '0.5'
    ) {
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

            if (!is_null($lastmod)) {
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

    /**
     * @param $locale
     * @return array
     */
    private function buildRouteParams(array $params, $locale)
    {
        return array_merge(
            ['_locale' => $locale],
            $params
        );
    }
}

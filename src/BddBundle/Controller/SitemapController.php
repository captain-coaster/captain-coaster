<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RiddenCoaster;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends Controller
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws InvalidArgumentException
     */
    public function indexAction()
    {
        $cache = new FilesystemAdapter();
        $urls = $cache->getItem('sitemap_urls');

        if (!$urls->isHit()) {
            $urls->set($this->getUrls());
            $urls->expiresAfter(\DateInterval::createFromDateString('12 hours'));
            $cache->save($urls);
        }

        return $this->render(
            'BddBundle:Sitemap:sitemap.xml.twig',
            ['urls' => $urls->get()]
        );
    }

    /**
     * @return array
     */
    public function getUrls()
    {
        $urls = [];

        // Latest review
        $latestRating = $this->getDoctrine()->getRepository('BddBundle:RiddenCoaster')
            ->findOneBy([], ['updatedAt' => 'desc'], 1);
        $indexUpdateDate = $latestRating->getUpdatedAt();

        // Index
        $indexUrls = $this->getUrlAndAlternates('bdd_index', [], $indexUpdateDate, 'daily', 1);
        $urls = array_merge($urls, $indexUrls);

        // Ranking
        $rankingUpdateDate = new \DateTime('first day of this month midnight');
        $rankingUrls = $this->getUrlAndAlternates('coaster_ranking', [], $rankingUpdateDate, 'monthly', 1);
        $urls = array_merge($urls, $rankingUrls);

        // Fiche Coasters
        $coasters = $this->getDoctrine()->getRepository(Coaster::class)->findAll();
        foreach ($coasters as $coaster) {
            $params = ['slug' => $coaster->getSlug()];
            $date = null;
            // Latest review
            $latestReview = $this->getDoctrine()->getRepository('BddBundle:RiddenCoaster')
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
     * @param $route
     * @param array $params
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
        $locales = $this->getParameter('app.locales.array');

        foreach ($locales as $locale) {
            $url = [];

            $url['loc'] = $this->generateUrl(
                $route,
                $this->buildRouteParams($params, $locale),
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $url['changefreq'] = $changefreq;
            $url['priority'] = $priority;

            if (!is_null($lastmod)) {
                $url['lastmod'] = $lastmod->format(\DateTime::W3C);
            }

            foreach ($locales as $alternateLocale) {
                if ($alternateLocale !== $locale) {
                    $url['alternate'][$alternateLocale] = $this->generateUrl(
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
     * @param array $params
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

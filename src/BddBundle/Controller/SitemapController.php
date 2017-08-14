<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RiddenCoaster;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends Controller
{
    public function indexAction()
    {
        $cache = new FilesystemAdapter();

        $urls = $cache->getItem('sitemap.xml');
        if (!$urls->isHit()) {
            $urls->set($this->getUrls());
            $urls->expiresAfter(\DateInterval::createFromDateString('4 hours'));
            $cache->save($urls);
        }

        return $this->render(
            'BddBundle:Sitemap:sitemap.xml.twig',
            [
                'urls' => $urls->get(),
            ]
        );
    }

    public function getUrls()
    {
        $urls = [];

        // Latest review
        $lastestReview = $this->getDoctrine()->getRepository('BddBundle:RiddenCoaster')
            ->findOneBy([], ['updatedAt' => 'desc'], 1);
        $date = $lastestReview->getUpdatedAt();

        // Index
        $indexUrls = $this->getUrlAndAlternates('bdd_index', [], $date, 'daily', 1);
        foreach ($indexUrls as $url) {
            $urls[] = $url;
        }

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
            foreach ($coasterUrls as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function getUrlAndAlternates(
        $route,
        array $params = [],
        \DateTime $lastmod = null,
        $changefreq = 'weekly',
        $priority = '0.5',
        array $locales = ["en", "fr"]
    ) {
        $urls = [];

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
                    $url['alternate']['locale'] = $alternateLocale;
                    $url['alternate']['loc'] = $this->generateUrl(
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

    private function buildRouteParams(array $params, $locale)
    {
        return array_merge(
            ['_locale' => $locale],
            $params
        );
    }
}

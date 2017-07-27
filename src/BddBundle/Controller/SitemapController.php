<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends Controller
{
    public function indexAction()
    {
        return $this->render(
            'BddBundle:Sitemap:sitemap.xml.twig',
            [
                'urls' => $this->getUrls(),
            ]
        );
    }

    public function getUrls()
    {
        $urls = [];

        // Index
        $indexUrls = $this->getUrlAndAlternates('bdd_index', [], 'daily', 1);
        foreach ($indexUrls as $url) {
            $urls[] = $url;
        }

        // Fiche Coasters
        $coasters = $this->getDoctrine()->getRepository(Coaster::class)->findAll();
        foreach ($coasters as $coaster) {
            $params = ['slug' => $coaster->getSlug()];
            $coasterUrls = $this->getUrlAndAlternates('bdd_show_coaster', $params, 'weekly', '0.8');
            foreach ($coasterUrls as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function getUrlAndAlternates(
        $route,
        array $params = [],
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

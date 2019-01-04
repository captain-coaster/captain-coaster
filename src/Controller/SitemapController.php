<?php

namespace App\Controller;

use App\Service\SitemapService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class SitemapController extends Controller
{
    /**
     * Create sitemap for pages
     *
     * @param SitemapService $sitemapService
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws InvalidArgumentException
     */
    public function indexAction(SitemapService $sitemapService)
    {
        $cache = new FilesystemAdapter();
        $urls = $cache->getItem('sitemap_urls');

        if (!$urls->isHit()) {
            $urls->set($sitemapService->getUrlsForPages());
            $urls->expiresAfter(\DateInterval::createFromDateString('12 hours'));
            $cache->save($urls);
        }

        return $this->render(
            'Sitemap/sitemap.xml.twig',
            ['urls' => $urls->get()]
        );
    }

    /**
     * Create sitemap for images
     *
     * @param SitemapService $sitemapService
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws InvalidArgumentException
     */
    public function imageAction(SitemapService $sitemapService)
    {
        $cache = new FilesystemAdapter();
        $urls = $cache->getItem('sitemap_image');

        if (!$urls->isHit()) {
            $urls->set($sitemapService->getUrlsForImages());
            $urls->expiresAfter(\DateInterval::createFromDateString('48 hours'));
            $cache->save($urls);
        }

        return $this->render(
            'Sitemap/sitemap_image.xml.twig',
            ['urls' => $sitemapService->getUrlsForImages()]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SitemapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends AbstractController
{
    /** Create sitemap for pages. */
    public function indexAction(SitemapService $sitemapService): Response
    {
        $cache = new FilesystemAdapter();
        $urls = $cache->getItem('sitemap_urls');

        if (!$urls->isHit()) {
            $urls->set($sitemapService->getUrlsForPages());
            $urls->expiresAfter(\DateInterval::createFromDateString('12 hours'));
            $cache->save($urls);
        }

        $response = $this->render(
            'Sitemap/sitemap.xml.twig',
            ['urls' => $urls->get()],
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }

    /** Create sitemap for images. */
    public function imageAction(SitemapService $sitemapService): Response
    {
        $cache = new FilesystemAdapter();
        $urls = $cache->getItem('sitemap_image');

        if (!$urls->isHit()) {
            $urls->set($sitemapService->getUrlsForImages());
            $urls->expiresAfter(\DateInterval::createFromDateString('48 hours'));
            $cache->save($urls);
        }

        $response =  $this->render(
            'Sitemap/sitemap_image.xml.twig',
            ['urls' => $sitemapService->getUrlsForImages()]
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}

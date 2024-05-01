<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SitemapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends AbstractController
{
    /** Retrieve sitemap for pages. */
    public function indexAction(SitemapService $sitemapService): Response
    {
        $cache = new FilesystemAdapter();
        $response = $this->render(
            'Sitemap/sitemap.xml.twig',
            ['urls' => $cache->getItem('sitemap_urls')->get()],
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }

    /** Retrieve sitemap for images. */
    public function imageAction(SitemapService $sitemapService): Response
    {
        $cache = new FilesystemAdapter();
        $response = $this->render(
            'Sitemap/sitemap_image.xml.twig',
            ['urls' => $cache->getItem('sitemap_image')->get()]
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}

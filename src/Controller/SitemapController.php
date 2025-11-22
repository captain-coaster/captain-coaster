<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SitemapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;

class SitemapController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'sitemap.cache')]
        private readonly CacheInterface $sitemapCache
    ) {
    }

    /** Retrieve sitemap for pages. */
    public function indexAction(SitemapService $sitemapService): Response
    {
        $urls = $this->sitemapCache->get('sitemap_urls', fn () => $sitemapService->getUrlsForPages());

        $response = $this->render('Sitemap/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }

    /** Retrieve sitemap for images. */
    public function imageAction(SitemapService $sitemapService): Response
    {
        $urls = $this->sitemapCache->get('sitemap_image', fn () => $sitemapService->getUrlsForImages());

        $response = $this->render('Sitemap/sitemap_image.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}

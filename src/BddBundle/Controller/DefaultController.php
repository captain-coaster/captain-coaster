<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tackk\Cartographer\ChangeFrequency;
use Tackk\Cartographer\Sitemap;

/**
 * Class DefaultController
 * @package BddBundle\Controller
 */
class DefaultController extends Controller
{
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function rootAction(Request $request)
    {
        $locale = $request->getPreferredLanguage(['en', 'fr']);

        return $this->redirectToRoute('bdd_index', ['_locale' => $locale], 301);
    }

    /**
     * Index of application
     *
     * @Route("/", name="bdd_index")
     * @Method({"GET"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $goodCoasters = [1985, 59, 205, 4, 128, 387, 240, 2138, 2197];
        $coasterId = $goodCoasters[array_rand($goodCoasters)];

        $ratingFeed = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->findBy([], ['updatedAt' => 'DESC'], 6);

        $images = $this->get('BddBundle\Service\ImageService')
            ->getCoasterImagesUrl($coasterId);

        $coaster = $this
            ->getDoctrine()
            ->getRepository('BddBundle:Coaster')
            ->findOneBy(['id' => $coasterId]);

        $stats = $this
            ->getDoctrine()
            ->getRepository('BddBundle:BuiltCoaster')
            ->getStats();

        $ratingNumber = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->countAll();

        $date = new \DateTime();
        $date->sub(new \DateInterval('P1D'));
        $newRatingNumber = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->countNew($date);

        return $this->render(
            'BddBundle:Default:index.html.twig',
            [
                'ratingFeed' => $ratingFeed,
                'images' => $images,
                'coaster' => $coaster,
                'stats' => $stats,
                'ratingNumber' => $ratingNumber,
                'newRatingNumber' => $newRatingNumber,
            ]
        );
    }

    /**
     * @Route("/sitemap.xml", name="sitemap")
     * @Method({"GET"})
     */
    public function sitemapAction()
    {
        $sitemap = new Sitemap();
        $sitemap->add($this->generateUrl('bdd_index'), null, ChangeFrequency::HOURLY, 1.0);

        $coasters = $this->getDoctrine()->getRepository(Coaster::class)->findAll();

        foreach ($coasters as $coaster) {
            $sitemap->add(
                $this->generateUrl('bdd_show_coaster', ['slug' => $coaster->getSlug()]),
                null,
                ChangeFrequency::WEEKLY,
                0.8
            );
        }

        // or simply echo it:
        return new Response($sitemap->toString(), 200, ['Content-Type' => 'text/xml']);
    }
}

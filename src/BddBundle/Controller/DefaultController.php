<?php

namespace BddBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

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
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/", name="bdd_index")
     * @Method({"GET"})
     */
    public function indexAction(Request $request)
    {
        $goodCoasters = [1985,2197,202,2183,2169,1572,1980,59,2028,2210,2130,196,2219,85,2247,795,2165,2000,2190,62,2192,2138];
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

        $reviews = $this
            ->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->getLatestReviewsByLocale($request->getLocale());

        return $this->render(
            'BddBundle:Default:index.html.twig',
            [
                'ratingFeed' => $ratingFeed,
                'images' => $images,
                'coaster' => $coaster,
                'stats' => $stats,
                'ratingNumber' => $ratingNumber,
                'newRatingNumber' => $newRatingNumber,
                'reviews' => $reviews,
            ]
        );
    }
}

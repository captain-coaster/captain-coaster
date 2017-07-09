<?php

namespace BddBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * Class DefaultController
 * @package BddBundle\Controller
 */
class DefaultController extends Controller
{
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
            ->getRepository('BddBundle:RatingCoaster')
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

        return $this->render(
            'BddBundle:Default:index.html.twig',
            [
                'ratingFeed' => $ratingFeed,
                'images' => $images,
                'coaster' => $coaster,
                'stats' => $stats
            ]
        );
    }
}

<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RiddenCoaster;
use BddBundle\Form\Type\ReviewType;
use BddBundle\Service\RatingService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ReviewController
 * @package BddBundle\Controller
 * @Route("/reviews")
 */
class ReviewController extends Controller
{
    /**
     * Show a list of reviews
     *
     * @Route("/{page}", name="review_list", requirements={"page" = "\d+"})
     * @Method({"GET"})
     *
     * @param Request $request
     * @param int $page
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request, $page = 1)
    {
        $query = $this->getDoctrine()
            ->getRepository('BddBundle:RiddenCoaster')
            ->findAll($request->getLocale());

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $page,
            10
        );

        return $this->render(
            '@Bdd/Review/list.html.twig',
            ['reviews' => $pagination]
        );
    }

    /**
     * Create or update a review
     *
     * @param Request $request
     * @param Coaster $coaster
     * @param RatingService $ratingService
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/coasters/{id}/form", name="review_form")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function newAction(Request $request, Coaster $coaster, RatingService $ratingService)
    {
        $em = $this->getDoctrine()->getManager();

        $review = $em->getRepository('BddBundle:RiddenCoaster')->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if (!$review instanceof RiddenCoaster) {
            $review = new RiddenCoaster();
            $review->setCoaster($coaster);
            $review->setUser($this->getUser());
        }

        /** @var Form $form */
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setLanguage($request->getLocale());
            $em = $this->getDoctrine()->getManager();
            $em->persist($review);
            $em->flush();

            // Update average rating on coaster
            // switch to event listener ?
            $ratingService->updateRatings();

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Review:form.html.twig',
            [
                'form' => $form->createView(),
                'coaster' => $coaster,
            ]
        );
    }
}

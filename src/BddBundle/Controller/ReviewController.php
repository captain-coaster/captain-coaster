<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RiddenCoaster;
use BddBundle\Form\Type\ReviewType;
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
     * @param Request $request
     * @param Coaster $coaster
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/coasters/{id}/form", name="review_form")
     * @Method({"GET", "POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function newAction(Request $request, Coaster $coaster)
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

            return $this->redirectToRoute('bdd_show_coaster', ['slug' => $coaster->getSlug()]);
        }

        return $this->render(
            'BddBundle:Review:form.html.twig',
            array(
                'form' => $form->createView(),
                'coaster' => $coaster,
            )
        );
    }
}

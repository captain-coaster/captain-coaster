<?php

namespace BddBundle\Controller;


use BddBundle\Entity\Coaster;
use BddBundle\Entity\RatingCoaster;
use BddBundle\Form\Type\RatingCoasterType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Class RatingCoasterController
 * @package BddBundle\Controller
 */
class RatingCoasterController extends Controller
{
    /**
     * @param Request $request
     * @param Coaster $coaster
     * @return JsonResponse|Response
     *
     * @Route(
     *     "/rating/coaster/{id}/edit",
     *     name="bdd_ajax_update_rating",
     *     options = {"expose" = true}
     * )
     * @Method({"GET", "POST"})
     */
    public function ajaxEditAction(Request $request, Coaster $coaster)
    {
        $em = $this->getDoctrine()->getManager();
        $rating = $em->getRepository('BddBundle:RatingCoaster')->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if(!$rating instanceof RatingCoaster) {
            $rating = new RatingCoaster();
        }

        $form = $this->createForm(RatingCoasterType::class, $rating);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rating->setUser($this->getUser());
            $rating->setCoaster($coaster);

            $em->persist($rating);
            $em->flush();

            // Update average rating on coaster
            $this->get('BddBundle\Service\RatingService')->manageRatings($coaster);

            return new JsonResponse(['status' => 'success']);
        }

        return $this->render(
            'BddBundle:Rating:form.html.twig',
            array(
                'form' => $form->createView(),
                'coasterId' => $coaster->getId(),
            )
        );
    }
}
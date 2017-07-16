<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RiddenCoaster;
use BddBundle\Form\Type\RatingCoasterType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
     * @Security("is_granted('ROLE_USER')")
     */
    public function ajaxEditAction(Request $request, Coaster $coaster)
    {
        $em = $this->getDoctrine()->getManager();
        $rating = $em->getRepository('BddBundle:RiddenCoaster')->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if (!$rating instanceof RiddenCoaster) {
            $rating = new RiddenCoaster();
        }

        $form = $this->createForm(RatingCoasterType::class, $rating);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rating->setUser($this->getUser());
            $rating->setCoaster($coaster);

            $em->persist($rating);
            $em->flush();

            // Update average rating on coaster
            // switch to event listener ?
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

    /**
     * @param Request $request
     * @param Coaster $coaster
     * @return JsonResponse
     *
     * @Route(
     *     "/ratings/coasters/{id}/edit",
     *     name="rating_edit",
     *     options = {"expose" = true}
     * )
     * @Method({"POST"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function editAction(Request $request, Coaster $coaster)
    {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        $rating = $em->getRepository('BddBundle:RiddenCoaster')->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if (!$rating instanceof RiddenCoaster) {
            $rating = new RiddenCoaster();
            $rating->setUser($user);
            $rating->setCoaster($coaster);
        }

        $rating->setValue($request->request->get('value'));

        $em->persist($rating);
        $em->flush();

        // Update average rating on coaster
        // switch to event listener ?
        $this->get('BddBundle\Service\RatingService')->manageRatings($coaster);

        return new JsonResponse(['state' => 'success']);
    }

    /**
     * @param Coaster $coaster
     * @return JsonResponse
     *
     * @Route(
     *     "/ratings/coasters/{id}",
     *     name="rating_delete",
     *     options = {"expose" = true}
     * )
     * @Method({"DELETE"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function deleteAction(Coaster $coaster)
    {
        $em = $this->getDoctrine()->getManager();

        $rating = $em->getRepository('BddBundle:RiddenCoaster')->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if (!$rating instanceof RiddenCoaster) {
            return new JsonResponse(['status' => 'fail']);
        }

        $em->remove($rating);
        $em->flush();

        // Update average rating on coaster
        // switch to event listener ?
        $this->get('BddBundle\Service\RatingService')->manageRatings($coaster);

        return new JsonResponse(['state' => 'success']);
    }
}
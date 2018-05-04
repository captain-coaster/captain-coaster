<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RiddenCoaster;
use BddBundle\Form\Type\RatingCoasterType;
use BddBundle\Service\RatingService;
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
     * Rate a coaster or edit a rating
     *
     * @param Request $request
     * @param Coaster $coaster
     * @param RatingService $ratingService
     * @return JsonResponse
     * @Route(
     *     "/ratings/coasters/{id}/edit",
     *     name="rating_edit",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"POST"})
     */
    public function editAction(Request $request, Coaster $coaster, RatingService $ratingService)
    {
        $this->denyAccessUnlessGranted('rate', $coaster);

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

        $validator = $this->get('validator');
        $errors = $validator->validate($rating);

        if (count($errors) > 0) {
            return new JsonResponse(['state' => 'error']);
        }

        $em->persist($rating);
        $em->flush();

        return new JsonResponse(['state' => 'success']);
    }

    /**
     * Delete a rating
     *
     * @param Coaster $coaster
     * @param RatingService $ratingService
     * @return JsonResponse
     * @Route(
     *     "/ratings/coasters/{id}",
     *     name="rating_delete",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"DELETE"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function deleteAction(Coaster $coaster, RatingService $ratingService)
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

        return new JsonResponse(['state' => 'success']);
    }
}

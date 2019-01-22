<?php

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class RatingCoasterController
 * @package App\Controller
 */
class RatingCoasterController extends AbstractController
{
    /**
     * Rate a coaster or edit a rating
     *
     * @param Request $request
     * @param Coaster $coaster
     * @param EntityManagerInterface $em
     * @param ValidatorInterface $validator
     * @return JsonResponse
     *
     * @Route(
     *     "/ratings/coasters/{id}/edit",
     *     name="rating_edit",
     *     methods={"POST"},
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Security("is_granted('ROLE_USER')")
     * @throws \Exception
     */
    public function editAction(
        Request $request,
        Coaster $coaster,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ) {
        $this->denyAccessUnlessGranted('rate', $coaster);

        /** @var User $user */
        $user = $this->getUser();

        $rating = $em->getRepository(RiddenCoaster::class)->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if (!$rating instanceof RiddenCoaster) {
            $rating = new RiddenCoaster();
            $rating->setUser($user);
            $rating->setCoaster($coaster);

            if($user->isAddTodayDateWhenRating()) {
                $rating->setRiddenAt(new \DateTime());
            }
        }

        if($request->request->has('value')) {
            $rating->setValue($request->request->get('value'));
        }

        if($request->request->has('riddenAt')) {
            $date = new \DateTime($request->request->get('riddenAt'));
            $rating->setRiddenAt($date);
        }

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
     * @return JsonResponse
     *
     * @Route(
     *     "/ratings/coasters/{id}",
     *     name="rating_delete",
     *     methods={"DELETE"},
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Security("is_granted('ROLE_USER')")
     */
    public function deleteAction(Coaster $coaster)
    {
        $em = $this->getDoctrine()->getManager();

        $rating = $em->getRepository('App:RiddenCoaster')->findOneBy(
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

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class RatingCoasterController.
 */
class RatingCoasterController extends AbstractController
{
    public $repository;

    public function __construct(private readonly \App\Repository\RiddenCoasterRepository $riddenCoasterRepository)
    {
    }

    /**
     * Rate a coaster or edit a rating.
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    #[Route(path: '/ratings/coasters/{id}/edit', name: 'rating_edit', methods: ['POST'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function editAction(
        Request $request,
        Coaster $coaster,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ) {
        $this->denyAccessUnlessGranted('rate', $coaster);

        /** @var User $user */
        $user = $this->getUser();

        $rating = $this->repository->findOneBy(
            ['coaster' => $coaster->getId(), 'user' => $this->getUser()->getId()]
        );

        if (!$rating instanceof RiddenCoaster) {
            $rating = new RiddenCoaster();
            $rating->setUser($user);
            $rating->setCoaster($coaster);
            $rating->setLanguage($request->getLocale());

            if ($user->isAddTodayDateWhenRating()) {
                $rating->setRiddenAt(new \DateTime());
            }
        }

        if ($request->request->has('value')) {
            $rating->setValue($request->request->get('value'));
        }

        if ($request->request->has('riddenAt')) {
            try {
                $date = new \DateTime($request->request->get('riddenAt'));
            } catch (\Exception) {
                return new JsonResponse(['state' => 'error'], \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $rating->setRiddenAt($date);
        }

        $errors = $validator->validate($rating);

        if (\count($errors) > 0) {
            return new JsonResponse(['state' => 'error']);
        }

        $em->persist($rating);
        $em->flush();

        return new JsonResponse([
            'state' => 'success',
            'id' => $rating->getId(),
        ]);
    }

    /**
     * Delete a rating.
     *
     * @return JsonResponse
     */
    #[Route(path: '/ratings/{id}', name: 'rating_delete', methods: ['DELETE'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function deleteAction(RiddenCoaster $rating, EntityManagerInterface $em)
    {
        $this->denyAccessUnlessGranted('delete', $rating);

        $em->remove($rating);
        $em->flush();

        return new JsonResponse(['state' => 'success']);
    }
}

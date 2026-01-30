<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RatingCoasterController extends AbstractController
{
    /** Rate a coaster or edit a rating. */
    #[Route(path: '/ratings/coasters/{id}/edit', name: 'rating_edit', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    #[IsGranted('rate', 'coaster', statusCode: 403)]
    public function editAction(
        Request $request,
        Coaster $coaster,
        EntityManagerInterface $em,
        RiddenCoasterRepository $riddenCoasterRepository,
        ValidatorInterface $validator,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$token || !$csrfTokenManager->isTokenValid(new CsrfToken('rating', (string) $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        $rating = $riddenCoasterRepository->findOneBy(
            ['coaster' => $coaster, 'user' => $this->getUser()]
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
            $rating->setValue((float) $request->request->get('value'));
        }

        if ($request->request->has('riddenAt')) {
            $riddenAtValue = $request->request->get('riddenAt');

            if (empty($riddenAtValue)) {
                // Clear the date if empty value is sent
                $rating->setRiddenAt(null);
            } else {
                try {
                    $date = new \DateTime((string) $riddenAtValue);
                    $rating->setRiddenAt($date);
                } catch (\Exception) {
                    return new JsonResponse(['state' => 'error'], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }
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

    /** Delete a rating. */
    #[Route(path: '/ratings/{id}', name: 'rating_delete', options: ['expose' => true], methods: ['DELETE'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    #[IsGranted('delete', 'rating', statusCode: 403)]
    public function deleteAction(
        Request $request,
        RiddenCoaster $rating,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$token || !$csrfTokenManager->isTokenValid(new CsrfToken('rating', (string) $token))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($rating);
        $em->flush();

        return new JsonResponse(['state' => 'success']);
    }
}

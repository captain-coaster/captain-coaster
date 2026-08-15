<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Service\RatingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RatingCoasterController extends AbstractController
{
    public function __construct(
        private readonly RatingService $ratingService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    /** Rate a coaster or mark as ridden. */
    #[Route(path: '/ratings/coasters/{id}/edit', name: 'rating_edit', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    #[IsGranted('rate', 'coaster', statusCode: 403)]
    public function editAction(Request $request, Coaster $coaster): JsonResponse
    {
        if (!$this->isValidCsrfToken($request)) {
            return $this->error('Invalid CSRF token', Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        if ('mark_ridden' === $request->request->get('action')) {
            $riddenCoaster = $this->ratingService->markAsRidden($user, $coaster);
        } else {
            $value = $request->request->get('value');
            if (!is_numeric($value) || !\in_array((float) $value, RiddenCoaster::ALLOWED_RATINGS, true)) {
                return $this->error('Invalid rating value', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $riddenCoaster = $this->ratingService->setRating($user, $coaster, (float) $value);
        }

        return new JsonResponse([
            'state' => 'success',
            'id' => $riddenCoaster->getId(),
        ]);
    }

    /** Delete a rating (remove ridden). */
    #[Route(path: '/ratings/{id}', name: 'rating_delete', options: ['expose' => true], methods: ['DELETE'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    #[IsGranted('delete', 'rating', statusCode: 403)]
    public function deleteAction(Request $request, RiddenCoaster $rating, LoggerInterface $logger): JsonResponse
    {
        if (!$this->isValidCsrfToken($request)) {
            $logger->error('Invalid CSRF token on rating_delete', [
                'referer' => $request->headers->get('referer', 'unknown'),
                'rating_id' => $rating->getId(),
            ]);

            return $this->error('Invalid CSRF token', Response::HTTP_FORBIDDEN);
        }

        $this->ratingService->removeRidden($rating);

        return new JsonResponse(['state' => 'success']);
    }

    /** Clear rating only (keep ridden status). */
    #[Route(path: '/ratings/{id}/clear-rating', name: 'rating_clear', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    #[IsGranted('update', 'riddenCoaster', statusCode: 403)]
    public function clearAction(Request $request, RiddenCoaster $riddenCoaster): JsonResponse
    {
        if (!$this->isValidCsrfToken($request)) {
            return $this->error('Invalid CSRF token', Response::HTTP_FORBIDDEN);
        }

        $this->ratingService->clearRating($riddenCoaster);

        return new JsonResponse(['state' => 'success']);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['state' => 'error', 'message' => $message], $status);
    }

    private function isValidCsrfToken(Request $request): bool
    {
        $token = $request->request->get('_token');

        return null !== $token && '' !== $token
            && $this->csrfTokenManager->isTokenValid(new CsrfToken('rating', (string) $token));
    }
}

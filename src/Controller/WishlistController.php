<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Coaster;
use App\Entity\User;
use App\Service\TopListService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WishlistController extends AbstractController
{
    public function __construct(
        private readonly TopListService $topListService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    /** Add a coaster to the bucket list. */
    #[Route(path: '/wishlist/{coasterId}', name: 'wishlist_add', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    public function addAction(Request $request, #[MapEntity(id: 'coasterId')] Coaster $coaster): JsonResponse
    {
        if (!$this->isValidCsrfToken($request)) {
            return new JsonResponse(['state' => 'error', 'message' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->topListService->addToBucket($user, $coaster);

        return new JsonResponse(['state' => 'success']);
    }

    /** Remove a coaster from the bucket list. */
    #[Route(path: '/wishlist/{coasterId}', name: 'wishlist_remove', options: ['expose' => true], methods: ['DELETE'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    public function removeAction(Request $request, #[MapEntity(id: 'coasterId')] Coaster $coaster): JsonResponse
    {
        if (!$this->isValidCsrfToken($request)) {
            return new JsonResponse(['state' => 'error', 'message' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->topListService->removeFromBucket($user, $coaster);

        return new JsonResponse(['state' => 'success']);
    }

    private function isValidCsrfToken(Request $request): bool
    {
        $token = $request->request->get('_token');

        return null !== $token && '' !== $token
            && $this->csrfTokenManager->isTokenValid(new CsrfToken('wishlist', (string) $token));
    }
}

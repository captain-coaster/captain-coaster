<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Top;
use App\Entity\User;
use App\Service\TopLikeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TopLikeController extends AbstractController
{
    public function __construct(
        private readonly TopLikeService $service,
    ) {
    }

    #[Route(path: '/tops/{id}/like', name: 'top_like', options: ['expose' => true], methods: ['POST'], condition: 'request.isXmlHttpRequest()')]
    #[IsGranted('ROLE_USER', statusCode: 403)]
    #[IsGranted('view', 'top', statusCode: 403)]
    public function toggle(Request $request, Top $top): JsonResponse
    {
        if (!$this->isCsrfTokenValid('top_like'.$top->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token');
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->service->canLike($top, $user)) {
            return new JsonResponse(['state' => 'error', 'message' => 'This list cannot be liked.'], 400);
        }

        $result = $this->service->toggle($user, $top);

        return new JsonResponse([
            'state' => 'success',
            'liked' => $result['liked'],
            'count' => $result['count'],
        ]);
    }
}

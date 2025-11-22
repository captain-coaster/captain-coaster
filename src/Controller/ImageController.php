<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Image;
use App\Service\ImageLikeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends BaseController
{
    #[Route(path: '/toggleLike/{id}', name: 'like_image_async', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function toggleLikeAction(
        Image $image,
        ImageLikeService $imageLikeService
    ): JsonResponse {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();

        // Prevent users from voting for their own pictures
        if ($image->getUploader() === $user) {
            return new JsonResponse(['error' => 'Cannot vote for your own picture'], Response::HTTP_FORBIDDEN);
        }

        $isLiked = $imageLikeService->toggleLike($image, $user);

        return new JsonResponse([
            'status' => 'ok',
            'liked' => $isLiked,
            'likeCount' => $image->getLikeCounter(),
        ]);
    }
}

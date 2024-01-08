<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Repository\LikedImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageController extends AbstractController
{
    #[Route(path: '/toggleLike/{id}', name: 'like_image_async', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function toggleLikeAction(
        Image $image,
        EntityManagerInterface $em,
        LikedImageRepository $likedImageRepository
    ): JsonResponse {
        if (!$this->isGranted('ROLE_USER')) {
            return new JsonResponse([], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        $likedImage = $likedImageRepository->findOneBy(['user' => $user, 'image' => $image]);

        if ($likedImage instanceof LikedImage) {
            $em->remove($likedImage);
        } else {
            $likedImage = new LikedImage();
            $likedImage->setUser($user);
            $likedImage->setImage($image);
            $em->persist($likedImage);
        }

        $em->flush();

        return new JsonResponse(['status' => 'ok']);
    }
}

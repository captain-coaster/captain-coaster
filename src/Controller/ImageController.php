<?php

namespace App\Controller;

use App\Entity\Image;
use App\Entity\LikedImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ImageController extends AbstractController
{
    /**
     * @return JsonResponse
     */
    #[Route(path: '/toggleLike/{id}', name: 'like_image_async', methods: ['GET'], options: ['expose' => true], condition: 'request.isXmlHttpRequest()')]
    public function toggleLikeAction(Image $image, EntityManagerInterface $em)
    {
        // avoid redirects to login...
        // @todo
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
            return new JsonResponse([], \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();
        $likedImage = $this->Repository->findOneBy(['user' => $user, 'image' => $image]);

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

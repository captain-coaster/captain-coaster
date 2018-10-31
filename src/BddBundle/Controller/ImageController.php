<?php

namespace BddBundle\Controller;

use BddBundle\Entity\Image;
use BddBundle\Entity\LikedImage;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class ImageController extends Controller
{
    /**
     * @Route(
     *     "/toggleLike/{id}",
     *     name="like_image_async",
     *     options = {"expose" = true},
     *     condition="request.isXmlHttpRequest()"
     * )
     * @Method({"GET"})
     * @param Image $image
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    public function toggleLikeAction(Image $image, EntityManagerInterface $em)
    {
        // avoid redirects to login...
        // @todo
        if (!$this->get('security.authorization_checker')->isGranted('ROLE_USER')) {
            return new JsonResponse([], 403);
        }

        $user = $this->getUser();
        $likedImage = $em->getRepository('BddBundle:LikedImage')->findOneBy(['user' => $user, 'image' => $image]);

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

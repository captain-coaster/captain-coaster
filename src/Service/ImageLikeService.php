<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Entity\LikedImage;
use App\Entity\User;
use App\Repository\LikedImageRepository;
use Doctrine\ORM\EntityManagerInterface;

class ImageLikeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LikedImageRepository $likedImageRepository
    ) {
    }

    /**
     * Toggle like for an image.
     * Returns true if liked, false if unliked.
     */
    public function toggleLike(Image $image, User $user): bool
    {
        $likedImage = $this->likedImageRepository->findOneBy(['user' => $user, 'image' => $image]);

        if ($likedImage instanceof LikedImage) {
            // Unlike: remove the like and decrement counter
            $this->entityManager->remove($likedImage);
            $image->setLikeCounter($image->getLikeCounter() - 1);
            $this->entityManager->flush();

            return false;
        }

        // Like: add the like and increment counter
        $likedImage = new LikedImage();
        $likedImage->setUser($user);
        $likedImage->setImage($image);
        $this->entityManager->persist($likedImage);
        $image->setLikeCounter($image->getLikeCounter() + 1);
        $this->entityManager->flush();

        return true;
    }

    /** Check if a user has liked an image. */
    public function hasUserLiked(Image $image, User $user): bool
    {
        return $this->likedImageRepository->hasUserLiked($user, $image);
    }

    /** Recalculate like counters for all images using repository method */
    public function updateAllLikeCounts(): void
    {
        $this->likedImageRepository->updateAllLikeCounts();
    }
}

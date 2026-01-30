<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ImageManager $imageManager,
        private readonly ProfilePictureManager $profilePictureManager
    ) {
    }

    public function scheduleAccountDeletion(User $user): void
    {
        $user->setDeletedAt(new \DateTime());
        $user->setEnabled(false);

        $this->em->persist($user);
        $this->em->flush();

        $this->logger->info('Account scheduled for deletion', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'deleted_at' => $user->getDeletedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    public function permanentlyDeleteAccount(User $user): void
    {
        $userId = $user->getId();
        $userEmail = $user->getEmail();

        // UserListener::preRemove calls deleteUserFiles()
        // Database cascade handles related entities
        $this->em->remove($user);
        $this->em->flush();

        $this->logger->info('Account permanently deleted', [
            'user_id' => $userId,
            'email' => $userEmail,
        ]);
    }

    /** Delete all user files from S3 (profile picture + uploaded images) */
    public function deleteUserFiles(User $user): void
    {
        $this->deleteProfilePicture($user);
        $this->deleteUserImages($user);
    }

    private function deleteProfilePicture(User $user): void
    {
        $profilePicture = $user->getProfilePicture();
        if (null !== $profilePicture) {
            $this->profilePictureManager->deleteProfilePicture($profilePicture);
        }
    }

    private function deleteUserImages(User $user): void
    {
        $images = $user->getImages();
        if (null === $images || 0 === $images->count()) {
            return;
        }

        /** @var Image $image */
        foreach ($images as $image) {
            try {
                $this->imageManager->remove($image->getFilename());
                $this->imageManager->removeCache($image);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to delete image file', [
                    'user_id' => $user->getId(),
                    'image_id' => $image->getId(),
                    'filename' => $image->getFilename(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

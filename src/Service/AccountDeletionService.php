<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\LikedImageRepository;
use App\Repository\ReviewUpvoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly LikedImageRepository $likedImageRepository,
        private readonly ReviewUpvoteRepository $reviewUpvoteRepository,
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

        // UserListener::preRemove calls UserFileDeletionService::deleteUserFiles()
        // Database cascade handles related entities
        $this->em->remove($user);
        $this->em->flush();

        $this->logger->info('Account permanently deleted', [
            'user_id' => $userId,
            'email' => $userEmail,
        ]);
    }

    /**
     * Purge all user data while keeping the user record (email, googleId).
     * Used for banned users to prevent re-registration.
     */
    public function purgeUserData(User $user): void
    {
        $userId = $user->getId();
        $userEmail = $user->getEmail();
        $profilePicture = $user->getProfilePicture();

        // Delete images (ImageListener handles S3 file deletion)
        foreach ($user->getImages() as $image) {
            $this->em->remove($image);
        }

        // Delete ratings/reviews (cascade will handle upvotes and reports)
        foreach ($user->getRatings() as $rating) {
            $this->em->remove($rating);
        }

        // Delete tops
        foreach ($user->getTops() as $top) {
            $this->em->remove($top);
        }

        // Delete notifications
        foreach ($user->getNotifications() as $notification) {
            $this->em->remove($notification);
        }

        // Delete liked images
        $this->likedImageRepository->deleteByUser($user);

        // Delete review upvotes given by this user
        $this->reviewUpvoteRepository->deleteByUser($user);

        // Clear badges
        $user->getBadges()->clear();

        // Delete profile picture from S3
        if (null !== $profilePicture) {
            $this->profilePictureManager->deleteProfilePicture($profilePicture);
        }

        // Clear non-essential personal info but keep identifiers and name
        $user->setProfilePicture(null);
        $user->setHomePark(null);

        $this->em->flush();
        $this->em->clear();

        $this->logger->info('Banned user data purged', [
            'user_id' => $userId,
            'email' => $userEmail,
        ]);
    }
}

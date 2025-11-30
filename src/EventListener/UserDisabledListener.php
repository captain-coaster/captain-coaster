<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\ImageLikeService;
use App\Service\ReviewScoreService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;

/**
 * Automatically clean up user interactions when a user is disabled.
 * Removes review upvotes and image likes to prevent disabled users from influencing rankings.
 */
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: User::class)]
class UserDisabledListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewScoreService $reviewScoreService,
        private readonly ImageLikeService $imageLikeService
    ) {
    }

    public function postUpdate(User $user): void
    {
        // Only process if user was just disabled
        if (!$user->isEnabled()) {
            $this->removeUserInteractions($user->getId());
        }
    }

    /** Remove all upvotes and image likes from a disabled user. */
    private function removeUserInteractions(int $userId): void
    {
        $conn = $this->entityManager->getConnection();

        // Remove all review upvotes from this user
        $conn->executeStatement(
            'DELETE FROM review_upvote WHERE user_id = :userId',
            ['userId' => $userId]
        );

        // Remove all image likes from this user
        $conn->executeStatement(
            'DELETE FROM liked_image WHERE user_id = :userId',
            ['userId' => $userId]
        );

        // Recalculate all image like counters
        $this->imageLikeService->updateAllLikeCounts();

        // Recalculate all review scores with weighted voting
        $this->reviewScoreService->updateAllScores();
    }
}

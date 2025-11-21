<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\ImageLikeService;
use App\Service\ReviewScoreService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Automatically clean up user interactions when a user is disabled.
 * Removes review upvotes and image likes to prevent disabled users from influencing rankings.
 */
#[AsDoctrineListener(event: Events::preUpdate, priority: 500, connection: 'default')]
class UserDisabledListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewScoreService $reviewScoreService,
        private readonly ImageLikeService $imageLikeService
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        // Only process User entities
        if (!$entity instanceof User) {
            return;
        }

        // Check if the 'enabled' field was changed to false
        if (!$args->hasChangedField('enabled')) {
            return;
        }

        $wasEnabled = $args->getOldValue('enabled');
        $isNowEnabled = $args->getNewValue('enabled');

        // Only act when user is being disabled (was enabled, now disabled)
        if ($wasEnabled && !$isNowEnabled) {
            $this->removeUserInteractions($entity);
        }
    }

    /** Remove all upvotes and image likes from a disabled user. */
    private function removeUserInteractions(User $user): void
    {
        $conn = $this->entityManager->getConnection();
        $userId = $user->getId();

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

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RiddenCoaster;
use App\Repository\ReviewUpvoteRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewScoreService
{
    // Constants for score calculation
    private const UPVOTE_WEIGHT = 10.0;
    private const RECENCY_WEIGHT = 5.0;
    private const RECENCY_DECAY_DAYS = 30.0; // How quickly recency factor decays

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewUpvoteRepository $upvoteRepository
    ) {
    }

    /** Calculate and update the score for a single review without affecting updatedAt */
    public function updateScore(RiddenCoaster $review): void
    {
        $score = $this->calculateScore($review);

        // Use direct SQL to update only the score field without triggering lifecycle callbacks
        $conn = $this->entityManager->getConnection();
        $sql = 'UPDATE ridden_coaster SET score = :score WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('score', $score);
        $stmt->bindValue('id', $review->getId());
        $stmt->executeStatement();

        // Update the entity in memory to reflect the database change
        $review->setScore($score);
    }

    /** Calculate and update scores for all reviews in one efficient SQL query */
    public function updateAllScores(): void
    {
        // Get connection for direct SQL execution
        $conn = $this->entityManager->getConnection();

        // Constants for the query
        $upvoteWeight = self::UPVOTE_WEIGHT;
        $recencyWeight = self::RECENCY_WEIGHT;
        $recencyDecayDays = self::RECENCY_DECAY_DAYS;

        // Build and execute the SQL query to update all scores at once
        $sql = "
            UPDATE ridden_coaster rc
            LEFT JOIN (
                SELECT
                    review_id,
                    COUNT(*) as upvote_count
                FROM review_upvote
                GROUP BY review_id
            ) ru ON rc.id = ru.review_id
            SET rc.score = (
                -- Upvote component: count * weight (default to 0 if NULL)
                COALESCE(ru.upvote_count, 0) * $upvoteWeight +

                -- Recency component: factor * weight
                -- Calculate recency factor: max(0, 1.0 - (daysDiff / decayDays))
                GREATEST(0, 1.0 - (DATEDIFF(NOW(), rc.updated_at) / $recencyDecayDays)) * $recencyWeight
            )
            WHERE rc.review IS NOT NULL
        ";

        // Execute the query
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();

        // Clear the entity manager to ensure entities are refreshed
        $this->entityManager->clear();
    }

    /**
     * Calculate the weighted score for a review.
     *
     * Formula: score = (upvotes * upvoteWeight) + (recencyFactor * recencyWeight)
     * where recencyFactor decreases as the review gets older
     */
    private function calculateScore(RiddenCoaster $review): float
    {
        // Get upvote count
        $upvoteCount = $this->upvoteRepository->countUpvotesForReview($review);

        // Calculate recency factor (1.0 for new reviews, decreasing over time)
        $now = new \DateTime();
        $reviewDate = $review->getUpdatedAt();
        $daysDiff = $now->diff($reviewDate)->days;
        $recencyFactor = max(0, 1.0 - ($daysDiff / self::RECENCY_DECAY_DAYS));

        // Calculate weighted score
        $score = ($upvoteCount * self::UPVOTE_WEIGHT) + ($recencyFactor * self::RECENCY_WEIGHT);

        return $score;
    }
}

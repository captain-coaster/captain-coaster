<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RiddenCoaster;
use Doctrine\ORM\EntityManagerInterface;

class ReviewScoreService
{
    // Constants for score calculation
    private const UPVOTE_WEIGHT = 10.0;
    private const RECENCY_WEIGHT = 5.0;
    private const RECENCY_DECAY_DAYS = 365.0; // Time decay over 1 year

    // User reputation thresholds
    private const MIN_REVIEWS_TO_VOTE = 1;    // Must have at least 1 review to vote
    private const NEW_USER_THRESHOLD = 5;     // Reviews count
    private const ACTIVE_USER_THRESHOLD = 25;
    private const VETERAN_USER_THRESHOLD = 100;

    // Vote weight multipliers based on reputation (very aggressive)
    private const NO_REVIEW_WEIGHT = 0.0;     // No reviews: vote doesn't count
    private const NEW_USER_WEIGHT = 0.2;      // 1-4 reviews: 20% weight
    private const REGULAR_USER_WEIGHT = 1.0;  // 5-24 reviews: 100% weight (baseline)
    private const ACTIVE_USER_WEIGHT = 2.0;   // 25-99 reviews: 200% weight
    private const VETERAN_USER_WEIGHT = 3.0;  // 100+ reviews: 300% weight

    public function __construct(
        private readonly EntityManagerInterface $entityManager
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

        // Reputation thresholds and weights
        $newUserThreshold = self::NEW_USER_THRESHOLD;
        $activeUserThreshold = self::ACTIVE_USER_THRESHOLD;
        $veteranUserThreshold = self::VETERAN_USER_THRESHOLD;
        $newUserWeight = self::NEW_USER_WEIGHT;
        $regularUserWeight = self::REGULAR_USER_WEIGHT;
        $activeUserWeight = self::ACTIVE_USER_WEIGHT;
        $veteranUserWeight = self::VETERAN_USER_WEIGHT;

        // Additional constants
        $minReviewsToVote = self::MIN_REVIEWS_TO_VOTE;
        $noReviewWeight = self::NO_REVIEW_WEIGHT;

        // Build and execute the SQL query to update all scores at once
        $sql = "
            UPDATE ridden_coaster rc
            LEFT JOIN (
                SELECT
                    ru.review_id,
                    SUM(
                        CASE
                            WHEN user_review_count < $minReviewsToVote THEN $noReviewWeight
                            WHEN user_review_count >= $veteranUserThreshold THEN $veteranUserWeight
                            WHEN user_review_count >= $activeUserThreshold THEN $activeUserWeight
                            WHEN user_review_count >= $newUserThreshold THEN $regularUserWeight
                            ELSE $newUserWeight
                        END
                    ) as weighted_upvotes
                FROM review_upvote ru
                INNER JOIN users u ON ru.user_id = u.id
                INNER JOIN (
                    SELECT u2.id as user_id, COUNT(rc2.id) as user_review_count
                    FROM users u2
                    LEFT JOIN ridden_coaster rc2 ON rc2.user_id = u2.id
                    WHERE u2.enabled = 1
                    GROUP BY u2.id
                ) user_stats ON ru.user_id = user_stats.user_id
                WHERE u.enabled = 1
                GROUP BY ru.review_id
            ) weighted_votes ON rc.id = weighted_votes.review_id
            SET rc.score = (
                -- Weighted upvote component (default to 0 if NULL)
                COALESCE(weighted_votes.weighted_upvotes, 0) * $upvoteWeight +

                -- Recency component: factor * weight
                -- Calculate recency factor: max(0, 1.0 - (daysDiff / decayDays))
                GREATEST(0, 1.0 - (DATEDIFF(NOW(), rc.updated_at) / $recencyDecayDays)) * $recencyWeight
            )
            WHERE rc.review IS NOT NULL
        ";

        // Execute the query
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement();
    }

    /**
     * Calculate the weighted score for a review.
     *
     * Formula: score = (weightedUpvotes * upvoteWeight) + (recencyFactor * recencyWeight)
     * where:
     * - weightedUpvotes considers voter reputation
     * - recencyFactor decreases as the review gets older
     */
    private function calculateScore(RiddenCoaster $review): float
    {
        // Get weighted upvote count (considers voter reputation)
        $weightedUpvotes = $this->calculateWeightedUpvotes($review);

        // Calculate recency factor (1.0 for new reviews, decreasing over time)
        $now = new \DateTime();
        $reviewDate = $review->getUpdatedAt();
        $daysDiff = $now->diff($reviewDate)->days;
        $recencyFactor = max(0, 1.0 - ($daysDiff / self::RECENCY_DECAY_DAYS));

        // Calculate weighted score
        $score = ($weightedUpvotes * self::UPVOTE_WEIGHT) + ($recencyFactor * self::RECENCY_WEIGHT);

        return $score;
    }

    /**
     * Calculate weighted upvotes based on voter reputation.
     * Users with more reviews have more voting power.
     * Users with 0 reviews cannot vote (weight = 0).
     * Disabled users' votes don't count.
     */
    private function calculateWeightedUpvotes(RiddenCoaster $review): float
    {
        $weightedTotal = 0.0;

        foreach ($review->getUpvotes() as $upvote) {
            $voter = $upvote->getUser();

            // Skip disabled users
            if (!$voter->isEnabled()) {
                continue;
            }

            $voterReviewCount = $voter->getRatings()->count();

            // Users with no reviews have zero voting power
            if ($voterReviewCount < self::MIN_REVIEWS_TO_VOTE) {
                continue;
            }

            $weight = match (true) {
                $voterReviewCount >= self::VETERAN_USER_THRESHOLD => self::VETERAN_USER_WEIGHT,
                $voterReviewCount >= self::ACTIVE_USER_THRESHOLD => self::ACTIVE_USER_WEIGHT,
                $voterReviewCount >= self::NEW_USER_THRESHOLD => self::REGULAR_USER_WEIGHT,
                default => self::NEW_USER_WEIGHT,
            };

            $weightedTotal += $weight;
        }

        return $weightedTotal;
    }
}

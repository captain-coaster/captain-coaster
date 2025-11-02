<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CoasterSummary;
use App\Entity\SummaryFeedback;
use App\Entity\User;
use App\Repository\SummaryFeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing user feedback on AI-generated coaster summaries.
 *
 * Handles feedback submission, duplicate vote prevention, vote changes,
 * and summary metrics calculation. Supports both authenticated and anonymous users.
 */
class SummaryFeedbackService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SummaryFeedbackRepository $feedbackRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Submits feedback for a summary, handling duplicate votes and vote changes.
     *
     * @param CoasterSummary $summary    The summary to provide feedback for
     * @param User|null      $user       The authenticated user (null for anonymous)
     * @param string         $ipAddress  The user's IP address
     * @param bool           $isPositive True for thumbs up, false for thumbs down
     *
     * @return SummaryFeedback The created or updated feedback entity
     */
    public function submitFeedback(CoasterSummary $summary, ?User $user, string $ipAddress, bool $isPositive): SummaryFeedback
    {
        // Hash IP address for privacy
        $hashedIp = $this->hashIpAddress($ipAddress);

        // Find existing feedback for this user/IP and summary
        $existingFeedback = $this->findExistingFeedback($summary, $user, $hashedIp);

        if ($existingFeedback) {
            // Update existing feedback if vote changed
            if ($existingFeedback->isPositive() !== $isPositive) {
                $this->logger->info('Updating existing feedback vote', [
                    'summary_id' => $summary->getId(),
                    'user_id' => $user?->getId(),
                    'old_vote' => $existingFeedback->isPositive() ? 'positive' : 'negative',
                    'new_vote' => $isPositive ? 'positive' : 'negative',
                ]);

                $existingFeedback->setIsPositive($isPositive);
                $this->entityManager->flush();

                // Recalculate summary metrics
                $this->updateSummaryMetrics($summary);
            }

            return $existingFeedback;
        }

        // Create new feedback
        $feedback = new SummaryFeedback();
        $feedback->setSummary($summary);
        $feedback->setUser($user);
        $feedback->setIpAddress($hashedIp);
        $feedback->setIsPositive($isPositive);

        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        $this->logger->info('New feedback submitted', [
            'summary_id' => $summary->getId(),
            'user_id' => $user?->getId(),
            'vote' => $isPositive ? 'positive' : 'negative',
        ]);

        // Recalculate summary metrics
        $this->updateSummaryMetrics($summary);

        return $feedback;
    }

    /**
     * Updates the feedback metrics for a summary by recalculating vote counts and ratio.
     *
     * @param CoasterSummary $summary The summary to update metrics for
     */
    public function updateSummaryMetrics(CoasterSummary $summary): void
    {
        // Count positive and negative votes directly from database
        $positiveCount = $this->feedbackRepository->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.summary = :summary')
            ->andWhere('f.isPositive = :positive')
            ->setParameter('summary', $summary)
            ->setParameter('positive', true)
            ->getQuery()
            ->getSingleScalarResult();

        $negativeCount = $this->feedbackRepository->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.summary = :summary')
            ->andWhere('f.isPositive = :positive')
            ->setParameter('summary', $summary)
            ->setParameter('positive', false)
            ->getQuery()
            ->getSingleScalarResult();

        // Update summary with new counts
        $summary->setPositiveVotes((int) $positiveCount);
        $summary->setNegativeVotes((int) $negativeCount);

        // Let the entity calculate the ratio
        $summary->updateFeedbackMetrics();

        $this->entityManager->flush();

        $this->logger->debug('Updated summary feedback metrics', [
            'summary_id' => $summary->getId(),
            'positive_votes' => $positiveCount,
            'negative_votes' => $negativeCount,
            'feedback_ratio' => $summary->getFeedbackRatio(),
        ]);
    }

    /**
     * Finds existing feedback for a user/IP and summary combination.
     *
     * @param CoasterSummary $summary  The summary to check
     * @param User|null      $user     The authenticated user (null for anonymous)
     * @param string         $hashedIp The hashed IP address
     *
     * @return SummaryFeedback|null The existing feedback or null if not found
     */
    private function findExistingFeedback(CoasterSummary $summary, ?User $user, string $hashedIp): ?SummaryFeedback
    {
        if ($user) {
            // For authenticated users, find by user and summary
            return $this->feedbackRepository->findOneBy([
                'summary' => $summary,
                'user' => $user,
            ]);
        }

        // For anonymous users, find by IP and summary
        return $this->feedbackRepository->findOneBy([
            'summary' => $summary,
            'ipAddress' => $hashedIp,
            'user' => null,
        ]);
    }

    /**
     * Hashes an IP address for privacy protection.
     *
     * @param string $ipAddress The raw IP address
     *
     * @return string The hashed IP address
     */
    private function hashIpAddress(string $ipAddress): string
    {
        // Use SHA-256 with a salt for privacy
        // This allows duplicate detection while protecting user privacy
        return hash('sha256', $ipAddress.'captain_coaster_feedback_salt');
    }

    /**
     * Gets the current feedback state for a user/IP and summary.
     *
     * @param CoasterSummary $summary   The summary to check
     * @param User|null      $user      The authenticated user (null for anonymous)
     * @param string         $ipAddress The user's IP address
     *
     * @return array{hasVoted: bool, isPositive: bool|null} The current vote state
     */
    public function getUserFeedbackState(CoasterSummary $summary, ?User $user, string $ipAddress): array
    {
        $hashedIp = $this->hashIpAddress($ipAddress);
        $existingFeedback = $this->findExistingFeedback($summary, $user, $hashedIp);

        if ($existingFeedback) {
            return [
                'hasVoted' => true,
                'isPositive' => $existingFeedback->isPositive(),
            ];
        }

        return [
            'hasVoted' => false,
            'isPositive' => null,
        ];
    }
}

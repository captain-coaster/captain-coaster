<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CoasterSummary;
use App\Service\SummaryFeedbackService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for handling user feedback on AI-generated coaster summaries.
 *
 * Provides endpoints for submitting thumbs up/down votes on summaries,
 * with support for both authenticated and anonymous users.
 */
#[Route(path: '/summary')]
class SummaryFeedbackController extends BaseController
{
    public function __construct(
        private SummaryFeedbackService $feedbackService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Submit feedback for a coaster summary.
     *
     * Accepts thumbs up/down votes from both authenticated and anonymous users.
     * Prevents duplicate votes and allows users to change their vote.
     */
    #[Route(path: '/{id}/feedback', name: 'summary_feedback', methods: ['POST'])]
    public function submitFeedback(
        Request $request,
        CoasterSummary $summary
    ): JsonResponse {
        // Validate CSRF token - use a consistent token ID for all summaries
        $token = $request->request->get('_token');
        $tokenId = 'summary_feedback';

        if (!$this->isCsrfTokenValid($tokenId, $token)) {
            $this->logger->warning('Invalid CSRF token for summary feedback', [
                'ip' => $request->getClientIp(),
                'summary_id' => $summary->getId(),
                'token_provided' => $token ? 'yes' : 'no',
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('ai_summary.feedback.error_session', [], 'ai_summary'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate feedback value
        $isPositive = $request->request->get('isPositive');
        if (!\is_string($isPositive) || !\in_array($isPositive, ['true', 'false'], true)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid feedback value.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $isPositive = 'true' === $isPositive;
        $user = $this->getUser();
        $ipAddress = $request->getClientIp();

        try {
            // Submit feedback through service
            $feedback = $this->feedbackService->submitFeedback($summary, $user, $ipAddress, $isPositive);

            // Refresh summary to get updated metrics
            $this->entityManager->refresh($summary);

            // Get user's current vote state
            $userFeedbackState = $this->feedbackService->getUserFeedbackState($summary, $user, $ipAddress);

            return new JsonResponse([
                'success' => true,
                'positiveVotes' => $summary->getPositiveVotes(),
                'negativeVotes' => $summary->getNegativeVotes(),
                'totalVotes' => $summary->getTotalVotes(),
                'feedbackRatio' => $summary->getFeedbackRatio(),
                'userVote' => $userFeedbackState['isPositive'],
                'hasVoted' => $userFeedbackState['hasVoted'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error submitting summary feedback', [
                'summary_id' => $summary->getId(),
                'user_id' => $user?->getId(),
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('ai_summary.feedback.error', [], 'ai_summary'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

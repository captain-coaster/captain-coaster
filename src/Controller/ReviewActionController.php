<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ReviewReport;
use App\Entity\ReviewUpvote;
use App\Entity\RiddenCoaster;
use App\Repository\ReviewReportRepository;
use App\Repository\ReviewUpvoteRepository;
use App\Service\ReviewScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/reviews')]
class ReviewActionController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewUpvoteRepository $upvoteRepository,
        private readonly ReviewReportRepository $reportRepository,
        private readonly ReviewScoreService $scoreService
    ) {
    }

    /** Toggle upvote for a review */
    #[Route(path: '/{id}/upvote', name: 'review_upvote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upvoteAction(Request $request, RiddenCoaster $review): JsonResponse
    {
        $user = $this->getUser();
        $hasUpvoted = $this->upvoteRepository->hasUserUpvoted($user, $review);

        if ($hasUpvoted) {
            // User has already upvoted, so remove the upvote
            $upvote = $this->upvoteRepository->findOneBy([
                'user' => $user,
                'review' => $review,
            ]);

            $this->entityManager->remove($upvote);
            $this->entityManager->flush();

            // Update the review score
            $this->scoreService->updateScore($review);

            return new JsonResponse([
                'success' => true,
                'action' => 'removed',
                'upvoteCount' => $review->getUpvoteCount(),
            ]);
        } else {
            // User hasn't upvoted yet, so add an upvote
            $upvote = new ReviewUpvote();
            $upvote->setUser($user);
            $upvote->setReview($review);

            $this->entityManager->persist($upvote);
            $this->entityManager->flush();

            // Update the review score
            $this->scoreService->updateScore($review);

            return new JsonResponse([
                'success' => true,
                'action' => 'added',
                'upvoteCount' => $review->getUpvoteCount(),
            ]);
        }
    }

    /** Report a review */
    #[Route(path: '/{id}/report', name: 'review_report', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportAction(Request $request, RiddenCoaster $review): JsonResponse
    {
        $user = $this->getUser();
        $hasReported = $this->reportRepository->hasUserReported($user, $review);

        if ($hasReported) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You have already reported this review',
            ], Response::HTTP_BAD_REQUEST);
        }

        $reason = $request->request->get('reason');

        if (!\in_array($reason, ReviewReport::REASONS, true)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid reason',
            ], Response::HTTP_BAD_REQUEST);
        }

        $report = new ReviewReport();
        $report->setUser($user);
        $report->setReview($review);
        $report->setReason($reason);
        $report->setCoaster($review->getCoaster());

        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Review reported successfully',
        ]);
    }

    /** Check if the current user has upvoted a review */
    #[Route(path: '/{id}/has-upvoted', name: 'review_has_upvoted', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function hasUpvotedAction(RiddenCoaster $review): JsonResponse
    {
        $user = $this->getUser();
        $hasUpvoted = $this->upvoteRepository->hasUserUpvoted($user, $review);

        return new JsonResponse([
            'hasUpvoted' => $hasUpvoted,
        ]);
    }
}

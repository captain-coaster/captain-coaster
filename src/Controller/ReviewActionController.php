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
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/reviews')]
class ReviewActionController extends BaseController
{
    public const REPORT_COMMENT_MIN = 10;
    public const REPORT_COMMENT_MAX = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewUpvoteRepository $upvoteRepository,
        private readonly ReviewReportRepository $reportRepository,
        private readonly ReviewScoreService $scoreService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** Toggle upvote for a review */
    #[Route(path: '/{id}/upvote', name: 'review_upvote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function upvoteAction(Request $request, RiddenCoaster $review): JsonResponse
    {
        $user = $this->getUser();

        // Prevent users from upvoting their own reviews
        if ($review->getUser() === $user) {
            return new JsonResponse(['error' => 'Cannot upvote your own review'], Response::HTTP_FORBIDDEN);
        }

        $hasUpvoted = $this->upvoteRepository->hasUserUpvoted($user, $review);

        if ($hasUpvoted) {
            // User has already upvoted, so remove the upvote
            $upvote = $this->upvoteRepository->findOneBy([
                'user' => $user,
                'review' => $review,
            ]);

            $this->entityManager->remove($upvote);
            $review->setUpvoteCounter($review->getUpvoteCounter() - 1);
            $this->entityManager->flush();

            // Update the review score
            $this->scoreService->updateScore($review);

            return new JsonResponse([
                'success' => true,
                'action' => 'removed',
                'upvoteCount' => $review->getUpvoteCount(),
            ]);
        }
        // User hasn't upvoted yet, so add an upvote
        $upvote = new ReviewUpvote();
        $upvote->setUser($user);
        $upvote->setReview($review);

        $this->entityManager->persist($upvote);
        $review->setUpvoteCounter($review->getUpvoteCounter() + 1);
        $this->entityManager->flush();

        // Update the review score
        $this->scoreService->updateScore($review);

        return new JsonResponse([
            'success' => true,
            'action' => 'added',
            'upvoteCount' => $review->getUpvoteCount(),
        ]);
    }

    /** Report a review */
    #[Route(path: '/{id}/report', name: 'review_report', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reportAction(Request $request, RiddenCoaster $review): JsonResponse
    {
        $user = $this->getUser();

        // A hidden text is not public; a rating alone can be reported.
        if ($review->isReviewHidden()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'This review is not published',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Prevent users from reporting their own reviews
        if ($review->getUser() === $user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cannot report your own review',
            ], Response::HTTP_FORBIDDEN);
        }

        $hasReported = $this->reportRepository->hasUserReported($user, $review);

        if ($hasReported) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You have already reported this review',
            ], Response::HTTP_BAD_REQUEST);
        }

        $reason = $request->request->get('reason');

        if (!\in_array($reason, ReviewReport::MEMBER_REASONS, true)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid reason',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Browsers send line breaks as CRLF but count them as one character against maxlength.
        $comment = trim(str_replace("\r\n", "\n", (string) $request->request->get('comment')));
        $length = mb_strlen($comment);
        if ($length < self::REPORT_COMMENT_MIN || $length > self::REPORT_COMMENT_MAX) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translator->trans('review.report_comment_invalid', ['min' => self::REPORT_COMMENT_MIN, 'max' => self::REPORT_COMMENT_MAX]),
            ], Response::HTTP_BAD_REQUEST);
        }

        $report = new ReviewReport();
        $report->setUser($user);
        $report->setReview($review);
        $report->setReason($reason);
        $report->setReporterComment($comment);

        // Store snapshot data in case the review gets deleted later
        $report->setReviewContent($review->getReview());
        $report->setCoasterName($review->getCoaster()->getName());
        $report->setReviewerName($review->getUser()->getDisplayName());
        $report->setReviewerId($review->getUser()->getId());
        $report->setRatingValue($review->getValue());

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

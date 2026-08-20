<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
use App\Repository\VocabularyGuideRepository;
use App\Service\BedrockService;
use App\Service\CoasterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the representative-sample backfill in CoasterSummaryService.
 * Below REPRESENTATIVE_SAMPLE_FLOOR (100) same-language reviews, the analysis set
 * should be topped up with other-language reviews, while the persisted
 * reviewsAnalyzed count stays same-language-only (it drives the regeneration
 * growth threshold, which must not be polluted by backfilled reviews).
 */
class CoasterSummaryServiceBackfillTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private CoasterSummaryService $service;
    private Coaster $coaster;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $vocabularyGuideRepository = $this->createMock(VocabularyGuideRepository::class);
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invokeModel')->willReturn([
            'success' => true,
            'content' => '{"summary": "Test summary", "pros": ["fast"], "cons": ["rough"]}',
            'metadata' => ['model' => 'gpt-5.6-luna'],
        ]);
        $logger = $this->createMock(LoggerInterface::class);

        $this->service = new CoasterSummaryService(
            $this->entityManager,
            $this->riddenCoasterRepository,
            $vocabularyGuideRepository,
            $bedrockService,
            $logger
        );

        $this->coaster = new Coaster();
        $this->coaster->setName('Test Coaster');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($repository);
    }

    /** @return array<int, RiddenCoaster> */
    private function makeReviews(int $count): array
    {
        $reviews = [];
        for ($i = 0; $i < $count; ++$i) {
            $review = new RiddenCoaster();
            $review->setReview("Review {$i}");
            $review->setCoaster($this->coaster);
            $reviews[] = $review;
        }

        return $reviews;
    }

    public function testBackfillIsFetchedWhenPrimaryReviewsBelowFloor(): void
    {
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturn(30);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->makeReviews(30));

        $this->riddenCoasterRepository
            ->expects($this->once())
            ->method('getCoasterReviewsWithTextExcludingLanguage')
            ->with($this->coaster, 'fr', 70) // 100 floor - 30 primary = 70
            ->willReturn($this->makeReviews(70));

        $result = $this->service->generateSummary($this->coaster, 'gpt-5.6-luna', 'fr');

        $this->assertNotNull($result['summary']);
        // Persisted count is primary-only, not the backfilled total (100)
        $this->assertSame(30, $result['summary']->getReviewsAnalyzed());
    }

    public function testBackfillIsNotFetchedWhenPrimaryReviewsMeetFloor(): void
    {
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturn(150);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->makeReviews(150));

        $this->riddenCoasterRepository
            ->expects($this->never())
            ->method('getCoasterReviewsWithTextExcludingLanguage');

        $result = $this->service->generateSummary($this->coaster, 'gpt-5.6-luna', 'fr');

        $this->assertNotNull($result['summary']);
        $this->assertSame(150, $result['summary']->getReviewsAnalyzed());
    }

    public function testPreviewSummaryReportsBothPrimaryAndTotalReviewCounts(): void
    {
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturn(25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->makeReviews(25));
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextExcludingLanguage')->willReturn($this->makeReviews(8));

        $result = $this->service->previewSummary($this->coaster, 'gpt-5.6-luna', 'fr');

        if (!isset($result['review_count'], $result['total_review_count'])) {
            $this->fail('Expected review_count and total_review_count to be present');
        }

        $this->assertSame(25, $result['review_count']);
        $this->assertSame(33, $result['total_review_count']);
    }

    public function testBackfillLimitIsCappedAtMaxReviewsForAnalysis(): void
    {
        // 0 primary reviews would naively ask for a 100-review backfill, but only
        // enough to reach REPRESENTATIVE_SAMPLE_FLOOR should ever be requested.
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturn(20);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->makeReviews(20));

        $this->riddenCoasterRepository
            ->expects($this->once())
            ->method('getCoasterReviewsWithTextExcludingLanguage')
            ->with($this->coaster, 'fr', 80)
            ->willReturn($this->makeReviews(80));

        $result = $this->service->previewSummary($this->coaster, 'gpt-5.6-luna', 'fr');

        if (!isset($result['total_review_count'])) {
            $this->fail('Expected total_review_count to be present');
        }

        $this->assertSame(100, $result['total_review_count']);
    }
}

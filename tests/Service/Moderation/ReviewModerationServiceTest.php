<?php

declare(strict_types=1);

namespace App\Tests\Service\Moderation;

use App\Entity\Coaster;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\ReviewReportRepository;
use App\Service\Ai\AiCallFailed;
use App\Service\Ai\AiClient;
use App\Service\Ai\AiResult;
use App\Service\Ai\AiTask;
use App\Service\Moderation\ReviewModerationService;
use App\Tests\AiStub;
use App\Tests\EntityId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ReviewModerationServiceTest extends TestCase
{
    private function service(
        AiClient $aiClient,
        ?ReviewReportRepository $reportRepository = null,
        ?EntityManagerInterface $entityManager = null,
    ): ReviewModerationService {
        return new ReviewModerationService(
            $aiClient,
            $reportRepository ?? $this->createMock(ReviewReportRepository::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
        );
    }

    /** @param array<string, mixed> $data */
    private function aiReturning(array $data): AiClient
    {
        $aiClient = $this->createMock(AiClient::class);
        $aiClient->method('run')->willReturn(AiStub::result($data));

        return $aiClient;
    }

    /** @return array{language: string, category: string, confidence: string, explanation: ?string} */
    private function verdict(string $category, string $confidence = 'high'): array
    {
        return ['language' => 'fr', 'category' => $category, 'confidence' => $confidence, 'explanation' => 'ok' === $category ? null : 'Why.'];
    }

    private function reviewWithAuthor(): RiddenCoaster
    {
        $review = $this->createReview('borderline text');
        $review->setUser(EntityId::set(new User(), 7)->setDisplayName('Jane'));

        return $review;
    }

    private function createReview(string $text, float $rating = 3.0): RiddenCoaster
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue($rating);
        $review->setReview($text);

        return EntityId::set($review, 1);
    }

    public function testAnalyzeReturnsParsedResultOnSuccess(): void
    {
        $service = $this->service($this->aiReturning(['language' => 'en', 'category' => 'toxic', 'confidence' => 'high', 'explanation' => 'Pure insult, no substance.']));
        $result = $service->analyze($this->createReview('i fucking hate this coaster', 1.0));

        $this->assertSame([
            'language' => 'en',
            'category' => 'toxic',
            'confidence' => 'high',
            'explanation' => 'Pure insult, no substance.',
        ], $result);
    }

    public function testAFailedCallIsThrownSoMessengerRetries(): void
    {
        $aiClient = $this->createMock(AiClient::class);
        $aiClient->method('run')->willThrowException(AiStub::failure(AiTask::ReviewModeration));

        $this->expectException(AiCallFailed::class);
        $this->service($aiClient)->analyze($this->createReview('a fine review'));
    }

    public function testInputPreservesAccentedCoasterNames(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Titánide');

        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue(3.0);
        $review->setReview('Bon manège');

        $input = null;
        $aiClient = $this->createMock(AiClient::class);
        $aiClient->method('run')->willReturnCallback(static function (AiTask $task, string $instructions, string $in) use (&$input): AiResult {
            $input = $in;

            return AiStub::result(['language' => 'fr', 'category' => 'ok', 'confidence' => 'high', 'explanation' => null]);
        });

        $service = $this->service($aiClient);
        $service->analyze($review);

        $this->assertStringContainsString('Titánide', $input);
        $this->assertStringNotContainsString('Titnide', $input);
    }

    public function testAnalyzeAcceptsNotRiddenCategory(): void
    {
        $service = $this->service($this->aiReturning(['language' => 'en', 'category' => 'not_ridden', 'confidence' => 'high', 'explanation' => 'Reviewer only comments on appearance, no ride experience described.']));
        $result = $service->analyze($this->createReview('it looks nice', 3.0));

        $this->assertSame([
            'language' => 'en',
            'category' => 'not_ridden',
            'confidence' => 'high',
            'explanation' => 'Reviewer only comments on appearance, no ride experience described.',
        ], $result);
    }

    public function testAnOkVerdictWithoutHighConfidenceBecomesUncertain(): void
    {
        $service = $this->service($this->aiReturning(['language' => 'en', 'category' => 'ok', 'confidence' => 'medium', 'explanation' => null]));

        $result = $service->analyze($this->createReview('meh'));

        $this->assertSame(ReviewReport::REASON_UNCERTAIN, $result['category']);
        $this->assertSame('No issue flagged, but the model was not highly confident (medium).', $result['explanation']);
    }

    public function testAFlagIsKeptWhateverTheConfidence(): void
    {
        $service = $this->service($this->aiReturning(['language' => 'en', 'category' => 'toxic', 'confidence' => 'low', 'explanation' => 'Maybe.']));

        $this->assertSame('toxic', $service->analyze($this->createReview('meh'))['category']);
    }

    public function testApplyResultPublishesAnOkReview(): void
    {
        $review = $this->reviewWithAuthor();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->service($this->createMock(AiClient::class), entityManager: $entityManager)->applyResult($review, $this->verdict('ok'));

        $this->assertTrue($review->isReviewEnabled());
        $this->assertNotNull($review->getAnalyzedAt());
        $this->assertSame('fr', $review->getLanguage());
    }

    public function testApplyResultKeepsAReviewWaitingForAModeratorHidden(): void
    {
        $review = $this->reviewWithAuthor();
        $reportRepository = $this->createMock(ReviewReportRepository::class);
        $reportRepository->method('hasUnresolvedReport')->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->service($this->createMock(AiClient::class), reportRepository: $reportRepository, entityManager: $entityManager)
            ->applyResult($review, $this->verdict('ok'));

        $this->assertFalse($review->isReviewEnabled());
        $this->assertNotNull($review->getAnalyzedAt());
    }

    public function testApplyResultHoldsAFlaggedReviewWithAReport(): void
    {
        $review = $this->reviewWithAuthor();
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')
            ->willReturnCallback(static function ($entity) use (&$persisted): void { $persisted = $entity; });

        $this->service($this->createMock(AiClient::class), entityManager: $entityManager)
            ->applyResult($review, $this->verdict(ReviewReport::REASON_UNCERTAIN, 'medium'));

        $this->assertFalse($review->isReviewEnabled());
        $this->assertInstanceOf(ReviewReport::class, $persisted);
        $this->assertNull($persisted->getUser());
        $this->assertSame(ReviewReport::REASON_UNCERTAIN, $persisted->getReason());
        $this->assertSame('medium', $persisted->getAiConfidence());
        $this->assertSame('borderline text', $persisted->getReviewContent());
        $this->assertSame(7, $persisted->getReviewerId());
        $this->assertSame(ReviewReport::STATUS_PENDING, $persisted->getStatus());
    }

    public function testApplyResultHidesAHighConfidenceFlagWithoutAModerator(): void
    {
        $review = $this->reviewWithAuthor();
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')
            ->willReturnCallback(static function ($entity) use (&$persisted): void { $persisted = $entity; });

        $this->service($this->createMock(AiClient::class), entityManager: $entityManager)
            ->applyResult($review, $this->verdict('toxic'));

        $this->assertFalse($review->isReviewEnabled());
        $this->assertSame(ReviewReport::STATUS_AUTO_HIDDEN, $persisted->getStatus());
        $this->assertTrue($persisted->isResolved());
        $this->assertTrue($persisted->isActionable());
    }

    public function testApplyResultDoesNotDuplicateAPendingAiReport(): void
    {
        $review = $this->reviewWithAuthor();
        $reportRepository = $this->createMock(ReviewReportRepository::class);
        $reportRepository->method('hasUnresolvedReport')->willReturn(true);
        $reportRepository->method('hasUnresolvedAiReport')->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->service($this->createMock(AiClient::class), reportRepository: $reportRepository, entityManager: $entityManager)
            ->applyResult($review, $this->verdict('toxic'));

        $this->assertFalse($review->isReviewEnabled());
    }

    public function testATrollFlagGoesToAModeratorEvenAtHighConfidence(): void
    {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function ($entity) use (&$persisted): void { $persisted = $entity; });

        $this->service($this->createMock(AiClient::class), entityManager: $entityManager)
            ->applyResult($this->reviewWithAuthor(), $this->verdict(ReviewReport::REASON_TROLL));

        $this->assertSame(ReviewReport::STATUS_PENDING, $persisted->getStatus());
    }

    public function testAHighConfidenceFlagJoinsTheQueueWhenAModeratorLooksAnyway(): void
    {
        $reportRepository = $this->createMock(ReviewReportRepository::class);
        $reportRepository->method('hasUnresolvedReport')->willReturn(true);
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function ($entity) use (&$persisted): void { $persisted = $entity; });

        $this->service($this->createMock(AiClient::class), reportRepository: $reportRepository, entityManager: $entityManager)
            ->applyResult($this->reviewWithAuthor(), $this->verdict('toxic'));

        $this->assertSame(ReviewReport::STATUS_PENDING, $persisted->getStatus());
    }
}

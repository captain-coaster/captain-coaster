<?php

declare(strict_types=1);

namespace App\Tests\Service\Summary;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Repository\RiddenCoasterRepository;
use App\Service\Ai\AiCallFailed;
use App\Service\Ai\AiClient;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiTask;
use App\Service\Summary\CoasterSummaryGenerator;
use App\Service\Summary\NotEnoughReviews;
use App\Service\Summary\SummaryPrompt;
use App\Tests\AiStub;
use App\Tests\EntityId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CoasterSummaryGeneratorTest extends TestCase
{
    private const array ANSWER = ['summary' => 'Generated summary', 'meta_description' => 'Riders love its airtime.', 'pros' => ['Pro 1', 'Pro 2'], 'cons' => ['Con 1']];

    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private AiClient&MockObject $aiClient;
    private CoasterSummaryGenerator $generator;
    private Coaster $coaster;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->aiClient = $this->createMock(AiClient::class);
        $this->generator = new CoasterSummaryGenerator($this->riddenCoasterRepository, $this->aiClient, $this->createMock(LoggerInterface::class));

        $this->coaster = EntityId::set(new Coaster(), 42);
        $this->coaster->setName('Test Coaster');
    }

    /** Every language is generated from its own reviews, none through English. */
    #[DataProvider('provideLanguages')]
    public function testSummaryIsGeneratedInTheAskedLanguageFromItsOwnReviews(string $language): void
    {
        $this->reviewCounts(25, 1000);
        $this->riddenCoasterRepository->expects($this->once())
            ->method('getCoasterReviewsWithTextByLanguage')
            ->with($this->coaster, $language, 600)
            ->willReturn($this->reviews(25));
        $this->aiClient->expects($this->once())
            ->method('run')
            ->with(
                AiTask::CoasterSummary,
                SummaryPrompt::instructions($language),
                $this->stringContains('Reminder: respond in '),
                SummaryPrompt::OUTPUT_SCHEMA,
                42,
                $language,
                null,
                null,
            )
            ->willReturn(AiStub::result(self::ANSWER));

        $generated = $this->generator->generate($this->coaster, $language);

        $this->assertSame('Generated summary', $generated->summary);
        $this->assertSame('Riders love its airtime.', $generated->metaDescription);
        $this->assertSame(['Pro 1', 'Pro 2'], $generated->pros);
        $this->assertSame(['Con 1'], $generated->cons);
        $this->assertSame(25, $generated->reviewsAnalyzed);
    }

    /** @return iterable<string, array{string}> */
    public static function provideLanguages(): iterable
    {
        yield 'en' => ['en'];
        yield 'fr' => ['fr'];
        yield 'es' => ['es'];
        yield 'de' => ['de'];
    }

    public function testModelIsPassedThroughAndKeptOnTheResult(): void
    {
        $this->reviewCounts(25, 25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(25));
        $this->aiClient->expects($this->once())
            ->method('run')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), AiModel::Gpt56Luna)
            ->willReturn(AiStub::result(self::ANSWER, AiModel::Gpt56Luna));

        $generated = $this->generator->generate($this->coaster, 'en', AiModel::Gpt56Luna);

        $this->assertSame(AiModel::Gpt56Luna, $generated->ai->model);
    }

    /** A preview is not recorded against the coaster, so it never counts as its summary's cost. */
    public function testPreviewHasNoSubjectId(): void
    {
        $this->reviewCounts(25, 25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(25));
        $this->aiClient->expects($this->once())
            ->method('run')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), null)
            ->willReturn(AiStub::result(self::ANSWER));

        $this->generator->generate($this->coaster, 'en', preview: true);
    }

    public function testEligibleWithFewNativeReviewsWhenTotalIsSufficient(): void
    {
        $this->reviewCounts(8, 25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(8));
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextExcludingLanguage')->willReturn($this->reviews(17));
        $this->aiClient->method('run')->willReturn(AiStub::result(self::ANSWER));

        $generated = $this->generator->generate($this->coaster, 'fr');

        $this->assertSame(8, $generated->reviewsAnalyzed);
        $this->assertSame(25, $generated->reviewsSent);
    }

    /** Below the native floor, backfill availability does not matter and the total is not even counted. */
    public function testNotEnoughNativeReviewsWhateverTheTotal(): void
    {
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturn(3);
        $this->riddenCoasterRepository->expects($this->never())->method('countAllReviewsWithText');
        $this->aiClient->expects($this->never())->method('run');

        try {
            $this->generator->generate($this->coaster, 'fr');
            $this->fail('Expected NotEnoughReviews');
        } catch (NotEnoughReviews $e) {
            $this->assertSame(3, $e->reviewCount);
        }
    }

    public function testNotEnoughReviewsInAllLanguagesEvenWithTheNativeFloorMet(): void
    {
        $this->reviewCounts(10, 12);
        $this->aiClient->expects($this->never())->method('run');

        try {
            $this->generator->generate($this->coaster, 'fr');
            $this->fail('Expected NotEnoughReviews');
        } catch (NotEnoughReviews $e) {
            $this->assertSame(12, $e->reviewCount);
        }
    }

    public function testBackfillTopsUpToTheSampleFloorAndReportsBothCounts(): void
    {
        $this->reviewCounts(30, 1000);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(30));
        $this->riddenCoasterRepository->expects($this->once())
            ->method('getCoasterReviewsWithTextExcludingLanguage')
            ->with($this->coaster, 'fr', 70)
            ->willReturn($this->reviews(70));
        $this->aiClient->method('run')->willReturn(AiStub::result(self::ANSWER));

        $generated = $this->generator->generate($this->coaster, 'fr');

        // Stored count is same-language only: it drives isDue().
        $this->assertSame(30, $generated->reviewsAnalyzed);
        $this->assertSame(100, $generated->reviewsSent);
    }

    public function testNoBackfillWhenSameLanguageReviewsMeetTheFloor(): void
    {
        $this->reviewCounts(150, 1000);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(150));
        $this->riddenCoasterRepository->expects($this->never())->method('getCoasterReviewsWithTextExcludingLanguage');
        $this->aiClient->method('run')->willReturn(AiStub::result(self::ANSWER));

        $generated = $this->generator->generate($this->coaster, 'fr');

        $this->assertSame(150, $generated->reviewsAnalyzed);
        $this->assertSame(150, $generated->reviewsSent);
    }

    public function testEmptySummaryFails(): void
    {
        $this->reviewCounts(25, 25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(25));
        $this->aiClient->method('run')->willReturn(AiStub::result(['summary' => '  ', 'meta_description' => '', 'pros' => [], 'cons' => []]));

        try {
            $this->generator->generate($this->coaster, 'en');
            $this->fail('Expected AiCallFailed');
        } catch (AiCallFailed $e) {
            $this->assertSame('EmptySummary', $e->errorCode);
        }
    }

    public function testAiFailurePropagates(): void
    {
        $this->reviewCounts(25, 25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(25));
        $failure = AiStub::failure(AiTask::CoasterSummary);
        $this->aiClient->method('run')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->generator->generate($this->coaster, 'en');
    }

    public function testMetaDescriptionIsCutToItsColumn(): void
    {
        $this->reviewCounts(25, 25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(25));
        $this->aiClient->method('run')->willReturn(AiStub::result([...self::ANSWER, 'meta_description' => str_repeat('word ', 80)]));

        $generated = $this->generator->generate($this->coaster, 'en');

        $this->assertSame(255, mb_strlen((string) $generated->metaDescription));
    }

    public function testBlankMetaDescriptionBecomesNull(): void
    {
        $this->reviewCounts(25, 25);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($this->reviews(25));
        $this->aiClient->method('run')->willReturn(AiStub::result([...self::ANSWER, 'meta_description' => ' ']));

        $this->assertNull($this->generator->generate($this->coaster, 'en')->metaDescription);
    }

    private function reviewCounts(int $native, int $total): void
    {
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturn($native);
        $this->riddenCoasterRepository->method('countAllReviewsWithText')->willReturn($total);
    }

    /** @return list<RiddenCoaster> */
    private function reviews(int $count): array
    {
        $reviews = [];
        for ($i = 0; $i < $count; ++$i) {
            $review = new RiddenCoaster();
            $review->setReview("Review {$i}");
            $review->setValue(4.0);
            $reviews[] = $review;
        }

        return $reviews;
    }
}

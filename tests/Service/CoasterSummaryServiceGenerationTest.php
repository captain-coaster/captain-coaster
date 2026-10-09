<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Repository\CoasterSummaryRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\BedrockService;
use App\Service\CoasterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** generateSummary() from the reviews to the stored CoasterSummary, the model call stubbed. */
class CoasterSummaryServiceGenerationTest extends TestCase
{
    private const int REVIEWS = 25;

    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private BedrockService&MockObject $bedrockService;
    private CoasterSummaryService $service;
    private string $prompt = '';
    /** @var list<string> */
    private array $countedLanguages = [];

    protected function setUp(): void
    {
        $summaries = $this->createMock(EntityRepository::class);
        $summaries->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($summaries);

        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->riddenCoasterRepository->method('countAllReviewsWithText')->willReturn(1000);
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')->willReturnCallback(function (Coaster $coaster, string $language): int {
            $this->countedLanguages[] = $language;

            return self::REVIEWS;
        });

        $this->bedrockService = $this->createMock(BedrockService::class);
        $this->bedrockService->method('invokeModel')->willReturnCallback(function (string $prompt): array {
            $this->prompt = $prompt;

            return [
                'success' => true,
                'content' => json_encode(['summary' => 'Generated summary', 'pros' => ['Pro 1', 'Pro 2'], 'cons' => ['Con 1']]),
                'metadata' => ['cost_usd' => 0.01],
            ];
        });

        $this->service = new CoasterSummaryService(
            $entityManager,
            $this->riddenCoasterRepository,
            $this->createMock(CoasterSummaryRepository::class),
            $this->bedrockService,
            $this->createMock(LoggerInterface::class),
        );
    }

    /** Every language is generated from its own reviews, none through English. */
    #[DataProvider('provideLanguages')]
    public function testSummaryIsGeneratedInTheAskedLanguageFromItsOwnReviews(string $language): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $this->riddenCoasterRepository
            ->expects($this->once())
            ->method('getCoasterReviewsWithTextByLanguage')
            ->with($coaster, $language, 600)
            ->willReturn($this->reviews($coaster));

        $summary = $this->service->generateSummary($coaster, 'gpt-5.6-luna', $language)['summary'];

        $this->assertInstanceOf(CoasterSummary::class, $summary);
        $this->assertSame($language, $summary->getLanguage());
        $this->assertSame([$language], array_values(array_unique($this->countedLanguages)));
        $this->assertSame('Generated summary', $summary->getSummary());
        $this->assertSame(['Pro 1', 'Pro 2'], $summary->getDynamicPros());
        $this->assertSame(['Con 1'], $summary->getDynamicCons());
        $this->assertSame(self::REVIEWS, $summary->getReviewsAnalyzed());
    }

    /** @return iterable<string, array{string}> */
    public static function provideLanguages(): iterable
    {
        yield 'en' => ['en'];
        yield 'fr' => ['fr'];
        yield 'es' => ['es'];
        yield 'de' => ['de'];
    }

    #[DataProvider('provideCoasterContexts')]
    public function testPromptCarriesTheCoasterContextAndEveryReview(string $status, string $averageRating, int $totalRatings, string $expectedRating): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $coaster->setAverageRating($averageRating);
        $coaster->setTotalRatings($totalRatings);
        $coaster->setStatus(new Status()->setName($status));
        $reviews = $this->reviews($coaster);
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')->willReturn($reviews);

        $this->service->generateSummary($coaster, 'gpt-5.6-luna', 'en');

        $this->assertMatchesRegularExpression('#<coaster_context>.*Status: '.preg_quote($status, '#').'.*</coaster_context>#s', $this->prompt);
        $this->assertStringContainsString($expectedRating, $this->prompt);
        $this->assertMatchesRegularExpression('#<review_data>.*</review_data>#s', $this->prompt);
        foreach ($reviews as $review) {
            $this->assertStringContainsString((string) $review->getReview(), $this->prompt);
        }
    }

    /** @return iterable<string, array{string, string, int, string}> */
    public static function provideCoasterContexts(): iterable
    {
        yield 'operating, top rated' => ['Operating', '5', 500, 'Community Rating: 100% based on 500 ratings'];
        yield 'closed, lowest rated' => ['Closed', '1', 20, 'Community Rating: 20% based on 20 ratings'];
        yield 'standing but not operating, decimal rating' => ['SBNO', '4.33', 137, 'Community Rating: 86.6% based on 137 ratings'];
    }

    /** @return list<RiddenCoaster> */
    private function reviews(Coaster $coaster): array
    {
        $reviews = [];
        for ($i = 0; $i < self::REVIEWS; ++$i) {
            $review = new RiddenCoaster();
            $review->setReview("This is test review {$i}");
            $review->setValue(4.0);
            $review->setCoaster($coaster);
            $reviews[] = $review;
        }

        return $reviews;
    }
}

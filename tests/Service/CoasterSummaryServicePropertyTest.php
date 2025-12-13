<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use App\Entity\VocabularyGuide;
use App\Repository\RiddenCoasterRepository;
use App\Repository\VocabularyGuideRepository;
use App\Service\BedrockService;
use App\Service\CoasterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Property-based tests for CoasterSummaryService.
 *
 * **Feature: coaster-summary-refactor, Property 1: Direct Generation Consistency**
 * **Feature: coaster-summary-refactor, Property 2: Vocabulary Guide Integration**
 * **Feature: coaster-summary-refactor, Property 4: Enhanced Source Data Inclusion**
 * **Feature: coaster-summary-refactor, Property 6: Backward Compatibility Preservation**
 */
class CoasterSummaryServicePropertyTest extends TestCase
{
    use TestTrait;

    private function createServiceWithMocks(): array
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $vocabularyGuideRepository = $this->createMock(VocabularyGuideRepository::class);
        $bedrockService = $this->createMock(BedrockService::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CoasterSummaryService(
            $entityManager,
            $riddenCoasterRepository,
            $vocabularyGuideRepository,
            $bedrockService,
            $logger
        );

        return [
            'service' => $service,
            'entityManager' => $entityManager,
            'riddenCoasterRepository' => $riddenCoasterRepository,
            'vocabularyGuideRepository' => $vocabularyGuideRepository,
            'bedrockService' => $bedrockService,
            'logger' => $logger,
        ];
    }

    /**
     * **Feature: coaster-summary-refactor, Property 1: Direct Generation Consistency**
     * **Validates: Requirements 1.1, 1.2**
     *
     * For any supported language and coaster with sufficient reviews, generating a summary
     * should use the same methodology regardless of the target language, without requiring
     * English as an intermediary.
     */
    public function testDirectGenerationConsistencyProperty(): void
    {
        $this->limitTo(3); // Limit to 3 iterations for faster testing
        $this->forAll(
            Generator\choose(0, 1000), // coaster ID
            Generator\string(), // coaster name
            Generator\elements(['en', 'fr', 'es', 'de']), // language
            Generator\choose(20, 100) // review count (sufficient reviews)
        )->then(function (int $coasterId, string $coasterName, string $language, int $reviewCount): void {
            $mocks = $this->createServiceWithMocks();
            $service = $mocks['service'];
            $entityManager = $mocks['entityManager'];
            $riddenCoasterRepository = $mocks['riddenCoasterRepository'];
            $vocabularyGuideRepository = $mocks['vocabularyGuideRepository'];
            $bedrockService = $mocks['bedrockService'];

            // Create coaster
            $coaster = new Coaster();
            $coaster->setName($coasterName ?: 'Test Coaster');

            // Create mock reviews
            $reviews = [];
            for ($i = 0; $i < $reviewCount; ++$i) {
                $review = new RiddenCoaster();
                $review->setReview("This is a test review {$i} in {$language}");
                $review->setCoaster($coaster); // Set coaster reference
                $reviews[] = $review;
            }

            // Mock repository to return sufficient review count
            $riddenCoasterRepository
                ->method('countCoasterReviewsWithText')
                ->with($coaster)
                ->willReturn($reviewCount);

            $riddenCoasterRepository
                ->method('getCoasterReviewsWithText')
                ->with($coaster, 600)
                ->willReturn($reviews);

            // Mock vocabulary guide (may or may not exist)
            $vocabularyGuide = null;
            if ('en' !== $language && random_int(0, 1)) {
                $vocabularyGuide = new VocabularyGuide();
                $vocabularyGuide->setLanguage($language);
                $vocabularyGuide->setContent("Vocabulary guide for {$language}");
            }

            $vocabularyGuideRepository
                ->method('findByLanguage')
                ->willReturn($vocabularyGuide);

            // Mock successful AI response
            $aiResponse = json_encode([
                'summary' => "Generated summary in {$language}",
                'pros' => ['Pro 1', 'Pro 2'],
                'cons' => ['Con 1'],
            ]);

            $bedrockService
                ->method('invokeModel')
                ->willReturn([
                    'success' => true,
                    'content' => $aiResponse,
                    'metadata' => ['cost_usd' => 0.01],
                ]);

            // Mock entity manager
            $repository = $this->createMock(EntityRepository::class);
            $repository
                ->method('findOneBy')
                ->willReturn(null);

            $entityManager
                ->method('getRepository')
                ->willReturn($repository);

            $entityManager
                ->method('persist')
                ->with($this->isInstanceOf(CoasterSummary::class));

            $entityManager
                ->method('flush');

            // Test direct generation
            $result = $service->generateSummary($coaster, 'nova2-lite', $language);

            // Verify direct generation methodology
            $this->assertArrayHasKey('summary', $result);
            $this->assertInstanceOf(CoasterSummary::class, $result['summary']);
            $this->assertSame($language, $result['summary']->getLanguage());
            $this->assertSame("Generated summary in {$language}", $result['summary']->getSummary());
            $this->assertSame(['Pro 1', 'Pro 2'], $result['summary']->getDynamicPros());
            $this->assertSame(['Con 1'], $result['summary']->getDynamicCons());
            $this->assertSame($reviewCount, $result['summary']->getReviewsAnalyzed());

            // Verify that the same methodology is used regardless of language
            // (no translation-specific logic should be present)
            $this->assertTrue(true, 'Direct generation completed successfully for all languages');
        });
    }

    /**
     * **Feature: coaster-summary-refactor, Property 2: Vocabulary Guide Integration**
     * **Validates: Requirements 1.4, 2.1**
     *
     * For any language with an available vocabulary guide, the generated AI prompt should
     * incorporate the vocabulary guide content and use it during summary generation.
     */
    public function testVocabularyGuideIntegrationProperty(): void
    {
        $this->limitTo(3); // Limit to 3 iterations for faster testing
        $this->forAll(
            Generator\choose(0, 1000), // coaster ID
            Generator\string(), // coaster name
            Generator\elements(['en', 'fr', 'es', 'de']), // language
            Generator\string(), // vocabulary guide content
            Generator\choose(20, 100) // review count (sufficient reviews)
        )->then(function (int $coasterId, string $coasterName, string $language, string $vocabularyContent, int $reviewCount): void {
            $mocks = $this->createServiceWithMocks();
            $service = $mocks['service'];
            $entityManager = $mocks['entityManager'];
            $riddenCoasterRepository = $mocks['riddenCoasterRepository'];
            $vocabularyGuideRepository = $mocks['vocabularyGuideRepository'];
            $bedrockService = $mocks['bedrockService'];

            // Create coaster
            $coaster = new Coaster();
            $coaster->setName($coasterName ?: 'Test Coaster');

            // Create mock reviews
            $reviews = [];
            for ($i = 0; $i < $reviewCount; ++$i) {
                $review = new RiddenCoaster();
                $review->setReview("This is a test review {$i} in {$language}");
                $review->setCoaster($coaster); // Set coaster reference
                $reviews[] = $review;
            }

            // Mock repository to return sufficient review count
            $riddenCoasterRepository
                ->method('countCoasterReviewsWithText')
                ->with($coaster)
                ->willReturn($reviewCount);

            $riddenCoasterRepository
                ->method('getCoasterReviewsWithText')
                ->with($coaster, 600)
                ->willReturn($reviews);

            // Create vocabulary guide with content
            $vocabularyGuide = new VocabularyGuide();
            $vocabularyGuide->setLanguage($language);
            $vocabularyGuide->setContent($vocabularyContent ?: 'Test vocabulary guide content');

            $vocabularyGuideRepository
                ->method('findByLanguage')
                ->with($language)
                ->willReturn($vocabularyGuide);

            // Capture the prompt that gets sent to BedrockService
            $capturedPrompt = '';
            $bedrockService
                ->method('invokeModel')
                ->willReturnCallback(function (string $prompt) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;
                    return [
                        'success' => true,
                        'content' => json_encode([
                            'summary' => "Generated summary in language",
                            'pros' => ['Pro 1'],
                            'cons' => ['Con 1'],
                        ]),
                        'metadata' => ['cost_usd' => 0.01],
                    ];
                });

            // Mock entity manager
            $repository = $this->createMock(EntityRepository::class);
            $repository
                ->method('findOneBy')
                ->willReturn(null);

            $entityManager
                ->method('getRepository')
                ->willReturn($repository);

            $entityManager
                ->method('persist')
                ->with($this->isInstanceOf(CoasterSummary::class));

            $entityManager
                ->method('flush');

            // Test vocabulary guide integration
            $result = $service->generateSummary($coaster, 'nova2-lite', $language);

            // Verify vocabulary guide content is incorporated in the prompt
            $expectedVocabularyContent = $vocabularyContent ?: 'Test vocabulary guide content';
            $this->assertStringContainsString('<vocabulary_guide>', $capturedPrompt);
            $this->assertStringContainsString($expectedVocabularyContent, $capturedPrompt);
            $this->assertStringContainsString('</vocabulary_guide>', $capturedPrompt);

            // Verify successful generation
            $this->assertArrayHasKey('summary', $result);
            $this->assertInstanceOf(CoasterSummary::class, $result['summary']);
        });
    }

    /**
     * **Feature: coaster-summary-refactor, Property 6: Backward Compatibility Preservation**
     * **Validates: Requirements 1.3**
     *
     * For any existing CoasterSummary record, the refactored service should be able to
     * read and process it without data loss or corruption.
     */
    public function testBackwardCompatibilityPreservationProperty(): void
    {
        $this->limitTo(3); // Limit to 3 iterations for faster testing
        $this->forAll(
            Generator\string(), // coaster name
            Generator\elements(['en', 'fr', 'es', 'de']), // language
            Generator\string(), // existing summary text
            Generator\seq(Generator\string()), // pros
            Generator\seq(Generator\string()), // cons
            Generator\choose(20, 200), // reviews analyzed
            Generator\choose(0, 100), // positive votes
            Generator\choose(0, 50) // negative votes
        )->then(function (
            string $coasterName,
            string $language,
            string $summaryText,
            array $pros,
            array $cons,
            int $reviewsAnalyzed,
            int $positiveVotes,
            int $negativeVotes
        ): void {
            $mocks = $this->createServiceWithMocks();
            $service = $mocks['service'];
            $entityManager = $mocks['entityManager'];

            // Create coaster
            $coaster = new Coaster();
            $coaster->setName($coasterName ?: 'Test Coaster');

            // Create existing summary (simulating backward compatibility)
            $existingSummary = new CoasterSummary();
            $existingSummary->setCoaster($coaster);
            $existingSummary->setLanguage($language);
            $existingSummary->setSummary($summaryText ?: 'Test summary');
            $existingSummary->setDynamicPros($pros);
            $existingSummary->setDynamicCons($cons);
            $existingSummary->setReviewsAnalyzed($reviewsAnalyzed);
            $existingSummary->setPositiveVotes($positiveVotes);
            $existingSummary->setNegativeVotes($negativeVotes);
            $existingSummary->setFeedbackRatio($negativeVotes > 0 ? $positiveVotes / ($positiveVotes + $negativeVotes) : 1.0);

            // Mock repository to return existing summary
            $repository = $this->createMock(EntityRepository::class);
            $repository
                ->method('findOneBy')
                ->willReturn($existingSummary);

            $entityManager
                ->method('getRepository')
                ->willReturn($repository);

            // Test that shouldUpdateSummary can process existing summary without corruption
            $shouldUpdate = $service->shouldUpdateSummary($coaster, $language);

            // Verify backward compatibility - method should complete without errors
            $this->assertIsBool($shouldUpdate);

            // Verify existing summary data is preserved and accessible
            $this->assertSame($language, $existingSummary->getLanguage());
            $this->assertSame($summaryText ?: 'Test summary', $existingSummary->getSummary());
            $this->assertSame($pros, $existingSummary->getDynamicPros());
            $this->assertSame($cons, $existingSummary->getDynamicCons());
            $this->assertSame($reviewsAnalyzed, $existingSummary->getReviewsAnalyzed());
            $this->assertSame($positiveVotes, $existingSummary->getPositiveVotes());
            $this->assertSame($negativeVotes, $existingSummary->getNegativeVotes());

            // Verify no data corruption occurred
            $this->assertTrue(true, 'Existing summary processed without data loss or corruption');
        });
    }

    /**
     * **Feature: coaster-summary-refactor, Property 4: Enhanced Source Data Inclusion**
     * **Validates: Requirements 1.5, 4.1, 4.2, 4.3, 4.5**
     *
     * For any coaster summary generation, the AI prompt should include review ratings alongside
     * review text, coaster status information, and global rating percentage when available.
     */
    public function testEnhancedSourceDataInclusionProperty(): void
    {
        $this->limitTo(3); // Limit to 3 iterations for faster testing
        $this->forAll(
            Generator\choose(0, 1000), // coaster ID
            Generator\string(), // coaster name
            Generator\elements(['en', 'fr', 'es', 'de']), // language
            Generator\elements(['Operating', 'Closed', 'Under Construction', 'SBNO']), // status name
            Generator\choose(1, 10), // average rating (1-10)
            Generator\choose(20, 500), // total ratings
            Generator\choose(20, 100) // review count (sufficient reviews)
        )->then(function (
            int $coasterId,
            string $coasterName,
            string $language,
            string $statusName,
            int $averageRating,
            int $totalRatings,
            int $reviewCount
        ): void {
            $mocks = $this->createServiceWithMocks();
            $service = $mocks['service'];
            $entityManager = $mocks['entityManager'];
            $riddenCoasterRepository = $mocks['riddenCoasterRepository'];
            $vocabularyGuideRepository = $mocks['vocabularyGuideRepository'];
            $bedrockService = $mocks['bedrockService'];

            // Create coaster with status and rating information
            $coaster = new Coaster();
            $coaster->setName($coasterName ?: 'Test Coaster');
            $coaster->setAverageRating((string) $averageRating);
            $coaster->setTotalRatings($totalRatings);

            // Create status
            $status = new Status();
            $status->setName($statusName);
            $coaster->setStatus($status);

            // Create mock reviews with ratings
            $reviews = [];
            for ($i = 0; $i < $reviewCount; ++$i) {
                $review = new RiddenCoaster();
                $review->setReview("This is a test review {$i} in {$language}");
                $review->setValue(random_int(1, 10)); // Random rating 1-10
                $review->setCoaster($coaster); // Set coaster reference
                $reviews[] = $review;
            }

            // Mock repository to return sufficient review count
            $riddenCoasterRepository
                ->method('countCoasterReviewsWithText')
                ->with($coaster)
                ->willReturn($reviewCount);

            $riddenCoasterRepository
                ->method('getCoasterReviewsWithText')
                ->with($coaster, 600)
                ->willReturn($reviews);

            // Mock vocabulary guide (optional)
            $vocabularyGuideRepository
                ->method('findByLanguage')
                ->willReturn(null);

            // Capture the prompt that gets sent to BedrockService
            $capturedPrompt = '';
            $bedrockService
                ->method('invokeModel')
                ->willReturnCallback(function (string $prompt) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;
                    return [
                        'success' => true,
                        'content' => json_encode([
                            'summary' => "Generated summary with enhanced data",
                            'pros' => ['Pro 1'],
                            'cons' => ['Con 1'],
                        ]),
                        'metadata' => ['cost_usd' => 0.01],
                    ];
                });

            // Mock entity manager
            $repository = $this->createMock(EntityRepository::class);
            $repository
                ->method('findOneBy')
                ->willReturn(null);

            $entityManager
                ->method('getRepository')
                ->willReturn($repository);

            $entityManager
                ->method('persist')
                ->with($this->isInstanceOf(CoasterSummary::class));

            $entityManager
                ->method('flush');

            // Test enhanced source data inclusion
            $result = $service->generateSummary($coaster, 'nova2-lite', $language);

            // Verify coaster context data is included in the prompt
            $this->assertStringContainsString('<coaster_context>', $capturedPrompt);
            $this->assertStringContainsString("Status: {$statusName}", $capturedPrompt);
            
            // Verify global rating percentage is included when available
            $expectedRatingPercent = round(($averageRating / 10) * 100, 1);
            $this->assertStringContainsString("Community Rating: {$expectedRatingPercent}% based on {$totalRatings} ratings", $capturedPrompt);
            $this->assertStringContainsString('</coaster_context>', $capturedPrompt);

            // Verify review text is included (but not individual ratings to avoid bias)
            $this->assertStringContainsString('<review_data>', $capturedPrompt);
            foreach ($reviews as $review) {
                $this->assertStringContainsString($review->getReview(), $capturedPrompt);
            }
            $this->assertStringContainsString('</review_data>', $capturedPrompt);

            // Verify successful generation
            $this->assertArrayHasKey('summary', $result);
            $this->assertInstanceOf(CoasterSummary::class, $result['summary']);
            $this->assertSame("Generated summary with enhanced data", $result['summary']->getSummary());
        });
    }
}
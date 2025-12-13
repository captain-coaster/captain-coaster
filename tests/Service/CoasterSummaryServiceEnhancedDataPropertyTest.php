<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\CoasterSummary;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Repository\RiddenCoasterRepository;
use App\Repository\VocabularyGuideRepository;
use App\Service\BedrockService;
use App\Service\CoasterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Property-based test for enhanced source data inclusion in CoasterSummaryService.
 *
 * **Feature: coaster-summary-refactor, Property 4: Enhanced Source Data Inclusion**
 */
class CoasterSummaryServiceEnhancedDataPropertyTest extends TestCase
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
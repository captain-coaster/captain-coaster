<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\Status;
use App\Repository\RiddenCoasterRepository;
use App\Service\BedrockService;
use App\Service\CoasterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CoasterSummaryService error handling scenarios.
 * Tests specific error conditions and their logging behavior.
 */
class CoasterSummaryServiceErrorHandlingTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private BedrockService&MockObject $bedrockService;
    private LoggerInterface&MockObject $logger;
    private CoasterSummaryService $service;
    private Coaster $coaster;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->bedrockService = $this->createMock(BedrockService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new CoasterSummaryService(
            $this->entityManager,
            $this->riddenCoasterRepository,
            $this->bedrockService,
            $this->logger
        );

        // Create test coaster
        $this->coaster = new Coaster();
        $this->coaster->setName('Test Coaster');
        $status = new Status();
        $status->setName('Operating');
        $this->coaster->setStatus($status);
    }

    /**
     * Test API failure logging with detailed context
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
     */
    public function testApiFailureLoggingWithDetailedContext(): void
    {
        // Setup sufficient reviews
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')
            ->willReturn(25);
        $this->riddenCoasterRepository->method('countAllReviewsWithText')
            ->willReturn(25);

        // Create a mock RiddenCoaster
        $mockRiddenCoaster = $this->createMock(\App\Entity\RiddenCoaster::class);
        $mockRiddenCoaster->method('getCoaster')->willReturn($this->coaster);
        $mockRiddenCoaster->method('getReview')->willReturn('Test review');
        $mockRiddenCoaster->method('getValue')->willReturn(8.0);
        
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')
            ->willReturn([$mockRiddenCoaster]);

// Setup Bedrock service to return failure
        $this->bedrockService->method('invokeModel')
            ->willReturn([
                'success' => false,
                'error' => 'API rate limit exceeded',
                'metadata' => ['model' => 'gpt-5.6-luna']
            ]);

        // Expect error logging with detailed context (both Bedrock service error and empty summary error)
        $this->logger->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function ($message, $context) {
                if ($message === 'Bedrock service error') {
                    $this->assertArrayHasKey('coaster', $context);
                    $this->assertArrayHasKey('error', $context);
                    $this->assertEquals('Test Coaster', $context['coaster']);
                    $this->assertEquals('API rate limit exceeded', $context['error']);
                } elseif ($message === 'AI analysis returned empty summary') {
                    $this->assertArrayHasKey('coaster', $context);
                    $this->assertEquals('Test Coaster', $context['coaster']);
                }
            });

        $result = $this->service->generateSummary($this->coaster, 'gpt-5.6-luna', 'en');

        $this->assertNull($result['summary']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertEquals('ai_error', $result['reason']); // @phpstan-ignore-line
    }


    /**
     * Test insufficient same-language reviews informational logging (below the
     * MIN_NATIVE_REVIEWS_REQUIRED floor - backfill can't help here, there just isn't
     * a genuine same-language audience yet).
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
     */
    public function testInsufficientNativeReviewsInformationalLogging(): void
    {
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')
            ->willReturn(3);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Not enough same-language reviews to generate summary',
                $this->callback(function ($context) {
                    $this->assertArrayHasKey('coaster', $context);
                    $this->assertArrayHasKey('reviews', $context);
                    $this->assertEquals('Test Coaster', $context['coaster']);
                    $this->assertEquals(3, $context['reviews']);
                    return true;
                })
            );

        $result = $this->service->generateSummary($this->coaster, 'gpt-5.6-luna', 'en');

        $this->assertNull($result['summary']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertEquals('insufficient_reviews', $result['reason']); // @phpstan-ignore-line
        $this->assertArrayHasKey('review_count', $result);
        $this->assertEquals(3, $result['review_count']); // @phpstan-ignore-line
    }

    /**
     * Test insufficient total (any-language) reviews informational logging - enough
     * of a genuine same-language base, but not enough overall content even with
     * backfill from other languages.
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
     */
    public function testInsufficientTotalReviewsInformationalLogging(): void
    {
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')
            ->willReturn(15);
        $this->riddenCoasterRepository->method('countAllReviewsWithText')
            ->willReturn(15);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Not enough reviews (any language) to generate summary',
                $this->callback(function ($context) {
                    $this->assertArrayHasKey('coaster', $context);
                    $this->assertArrayHasKey('reviews', $context);
                    $this->assertEquals('Test Coaster', $context['coaster']);
                    $this->assertEquals(15, $context['reviews']);
                    return true;
                })
            );

        $result = $this->service->generateSummary($this->coaster, 'gpt-5.6-luna', 'en');

        $this->assertNull($result['summary']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertEquals('insufficient_reviews', $result['reason']); // @phpstan-ignore-line
        $this->assertArrayHasKey('review_count', $result);
        $this->assertEquals(15, $result['review_count']); // @phpstan-ignore-line
    }

    /**
     * Test AI response parsing failure logging
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
     */
    public function testAiResponseParsingFailureLogging(): void
    {
        // Setup sufficient reviews
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')
            ->willReturn(25);
        $this->riddenCoasterRepository->method('countAllReviewsWithText')
            ->willReturn(25);

        // Create a mock RiddenCoaster
        $mockRiddenCoaster = $this->createMock(\App\Entity\RiddenCoaster::class);
        $mockRiddenCoaster->method('getCoaster')->willReturn($this->coaster);
        $mockRiddenCoaster->method('getReview')->willReturn('Test review');
        $mockRiddenCoaster->method('getValue')->willReturn(8.0);
        
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')
            ->willReturn([$mockRiddenCoaster]);

// Setup Bedrock service to return malformed JSON that will trigger JsonException
        $this->bedrockService->method('invokeModel')
            ->willReturn([
                'success' => true,
                'content' => '{"summary": "Test summary", "pros": ["fast"], "cons": [invalid json}',
                'metadata' => ['model' => 'gpt-5.6-luna']
            ]);

        // Expect warning logging for JSON parsing failure
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to parse AI response JSON',
                $this->callback(function ($context) {
                    $this->assertArrayHasKey('error', $context);
                    $this->assertIsString($context['error']);
                    return true;
                })
            );

        // Also expect error logging for empty summary result
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'AI analysis returned empty summary',
                $this->callback(function ($context) {
                    $this->assertArrayHasKey('coaster', $context);
                    $this->assertEquals('Test Coaster', $context['coaster']);
                    return true;
                })
            );

        $result = $this->service->generateSummary($this->coaster, 'gpt-5.6-luna', 'en');

        $this->assertNull($result['summary']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertEquals('ai_error', $result['reason']); // @phpstan-ignore-line
    }

    /**
     * Test successful parsing with valid JSON does not trigger parsing error
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
     */
    public function testSuccessfulParsingDoesNotTriggerError(): void
    {
        // Setup sufficient reviews
        $this->riddenCoasterRepository->method('countCoasterReviewsWithTextByLanguage')
            ->willReturn(25);
        $this->riddenCoasterRepository->method('countAllReviewsWithText')
            ->willReturn(25);

        // Create a mock RiddenCoaster
        $mockRiddenCoaster = $this->createMock(\App\Entity\RiddenCoaster::class);
        $mockRiddenCoaster->method('getCoaster')->willReturn($this->coaster);
        $mockRiddenCoaster->method('getReview')->willReturn('Test review');
        $mockRiddenCoaster->method('getValue')->willReturn(8.0);
        
        $this->riddenCoasterRepository->method('getCoasterReviewsWithTextByLanguage')
            ->willReturn([$mockRiddenCoaster]);

// Setup Bedrock service to return valid JSON
        $this->bedrockService->method('invokeModel')
            ->willReturn([
                'success' => true,
                'content' => '{"summary": "Great coaster with amazing airtime", "pros": ["fast", "smooth"], "cons": ["long queue"]}',
                'metadata' => ['model' => 'gpt-5.6-luna']
            ]);

        // Expect NO warning or error logging for successful parsing
        $this->logger->expects($this->never())
            ->method('warning');
        $this->logger->expects($this->never())
            ->method('error');

        $result = $this->service->generateSummary($this->coaster, 'gpt-5.6-luna', 'en');

        $this->assertNotNull($result['summary']);
        $this->assertArrayNotHasKey('reason', $result);
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Service\BedrockService;
use App\Service\ReviewModerationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReviewModerationServiceTest extends TestCase
{
    private function createReview(string $text, float $rating = 3.0): RiddenCoaster
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue($rating);
        $review->setReview($text);

        // Set ID via reflection for testing purposes
        $reflection = new \ReflectionClass($review);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($review, 1);

        return $review;
    }

    public function testAnalyzeReturnsParsedResultOnSuccess(): void
    {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invokeModel')->willReturn([
            'success' => true,
            'content' => '{"language":"en","category":"toxic","confidence":"high","explanation":"Pure insult, no substance."}',
            'metadata' => [],
        ]);

        $service = new ReviewModerationService($bedrockService, $this->createMock(LoggerInterface::class));
        $result = $service->analyze($this->createReview('i fucking hate this coaster', 1.0));

        $this->assertSame([
            'language' => 'en',
            'category' => 'toxic',
            'confidence' => 'high',
            'explanation' => 'Pure insult, no substance.',
        ], $result);
    }

    public function testAnalyzeReturnsNullAndLogsOnBedrockFailure(): void
    {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invokeModel')->willReturn([
            'success' => false,
            'error' => 'Throttled',
            'error_code' => 'ThrottlingException',
            'metadata' => [],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Review moderation Bedrock call failed');

        $service = new ReviewModerationService($bedrockService, $logger);
        $result = $service->analyze($this->createReview('a fine review'));

        $this->assertNull($result);
    }

    public function testAnalyzeReturnsNullAndLogsOnUnparsableResponse(): void
    {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invokeModel')->willReturn([
            'success' => true,
            'content' => 'not json at all',
            'metadata' => [],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Review moderation response could not be parsed');

        $service = new ReviewModerationService($bedrockService, $logger);
        $result = $service->analyze($this->createReview('a fine review'));

        $this->assertNull($result);
    }

    public function testAnalyzeToleratesReasoningPrefixAndInvalidConfidence(): void
    {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invokeModel')->willReturn([
            'success' => true,
            'content' => "<reasoning>thinking about it...</reasoning>\n".
                '{"language":"fr","category":"ok","confidence":"very-high","explanation":null}',
            'metadata' => [],
        ]);

        $service = new ReviewModerationService($bedrockService, $this->createMock(LoggerInterface::class));
        $result = $service->analyze($this->createReview('super manège'));

        $this->assertSame('fr', $result['language']);
        $this->assertSame('ok', $result['category']);
        $this->assertNull($result['confidence']); // invalid enum value -> discarded, not trusted
        $this->assertNull($result['explanation']);
    }

    public function testAnalyzeRejectsUnknownCategory(): void
    {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invokeModel')->willReturn([
            'success' => true,
            'content' => '{"language":"en","category":"not-a-real-category","confidence":"high","explanation":"x"}',
            'metadata' => [],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $service = new ReviewModerationService($bedrockService, $logger);
        $result = $service->analyze($this->createReview('a fine review'));

        $this->assertNull($result);
    }
}

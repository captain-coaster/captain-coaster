<?php

declare(strict_types=1);

namespace App\Tests\Service\Moderation;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Service\Ai\AiTask;
use App\Service\Ai\BedrockService;
use App\Service\Moderation\ReviewModerationService;
use App\Tests\EntityId;
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

        return EntityId::set($review, 1);
    }

    public function testAnalyzeReturnsParsedResultOnSuccess(): void
    {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invoke')->willReturn([
            'success' => true,
            'data' => ['language' => 'en', 'category' => 'toxic', 'confidence' => 'high', 'explanation' => 'Pure insult, no substance.'],
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
        $bedrockService->method('invoke')->willReturn([
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

    public function testAnalyzeHandlesUnpersistedEntityOnBedrockFailure(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        // Create a truly unpersisted review with no ID injection
        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue(3.0);
        $review->setReview('test review');

        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invoke')->willReturn([
            'success' => false,
            'error' => 'Throttled',
            'error_code' => 'ThrottlingException',
            'metadata' => [],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Review moderation Bedrock call failed');

        $service = new ReviewModerationService($bedrockService, $logger);
        $result = $service->analyze($review);

        $this->assertNull($result);
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
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invoke')->willReturnCallback(function (AiTask $task, string $instructions, string $in) use (&$input): array {
            $input = $in;

            return ['success' => true, 'data' => ['language' => 'fr', 'category' => 'ok', 'confidence' => 'high', 'explanation' => null], 'metadata' => []];
        });

        $service = new ReviewModerationService($bedrockService, $this->createMock(LoggerInterface::class));
        $service->analyze($review);

        $this->assertStringContainsString('Titánide', $input);
        $this->assertStringNotContainsString('Titnide', $input);
    }

    public function testAnalyzeAcceptsNotRiddenCategory(): void
    {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invoke')->willReturn([
            'success' => true,
            'data' => ['language' => 'en', 'category' => 'not_ridden', 'confidence' => 'high', 'explanation' => 'Reviewer only comments on appearance, no ride experience described.'],
            'metadata' => [],
        ]);

        $service = new ReviewModerationService($bedrockService, $this->createMock(LoggerInterface::class));
        $result = $service->analyze($this->createReview('it looks nice', 3.0));

        $this->assertSame([
            'language' => 'en',
            'category' => 'not_ridden',
            'confidence' => 'high',
            'explanation' => 'Reviewer only comments on appearance, no ride experience described.',
        ], $result);
    }
}

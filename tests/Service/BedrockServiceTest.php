<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BedrockService;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * **Feature: coaster-summary-refactor, Property 3: Unified Bedrock API Interface**
 *
 * Tests that the BedrockService uses the same Converse API request format
 * and response parsing method regardless of the underlying model.
 */
class BedrockServiceTest extends TestCase
{
    use TestTrait;

    /**
     * **Property 3: Unified Bedrock API Interface**
     * **Validates: Requirements 3.2, 3.3, 3.4, 3.5**
     *
     * For any supported Bedrock model (Nova 2 Lite, Claude Haiku, GPT OSS),
     * the BedrockService should use the same Converse API request format
     * and response parsing method regardless of the underlying model.
     */
    public function testUnifiedBedrockApiInterface(): void
    {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(['nova2-lite', 'claude-haiku-4.5', 'gpt-oss-120b']), // @phpstan-ignore-line
            Generator\string(), // @phpstan-ignore-line
            Generator\choose(100, 2000), // @phpstan-ignore-line
            Generator\float(0.0, 1.0) // @phpstan-ignore-line
        )
        ->then(function (string $model, string $prompt, int $maxTokens, float $temperature) {
            // Create an anonymous class extending BedrockRuntimeClient to mock converse()
            $bedrockClient = new class extends BedrockRuntimeClient {
                /** @var array<string, mixed>|null */
                public ?array $lastConverseArgs = null;
                private Result $mockResult;

                public function __construct()
                {
                    // Skip parent constructor - we don't need actual AWS connection
                }

                public function setMockResult(Result $result): void
                {
                    $this->mockResult = $result;
                }

                public function converse(array $args = []): Result
                {
                    $this->lastConverseArgs = $args;

                    return $this->mockResult;
                }
            };

            $logger = $this->createMock(LoggerInterface::class);
            $service = new BedrockService($bedrockClient, $logger, 'nova2-lite');

            // Set up mock response
            $mockResult = $this->createMockBedrockResponse($model);
            $bedrockClient->setMockResult($mockResult);

            $result = $service->invokeModel($prompt, $model, $maxTokens, $temperature);

            // Verify the Converse API format was used
            $args = $bedrockClient->lastConverseArgs;
            $this->assertNotNull($args);
            $this->assertArrayHasKey('modelId', $args);
            $this->assertArrayHasKey('messages', $args);
            $this->assertIsArray($args['messages']);
            $this->assertCount(1, $args['messages']);

            $message = $args['messages'][0];
            $this->assertEquals('user', $message['role']);
            $this->assertArrayHasKey('content', $message);

            $this->assertArrayHasKey('inferenceConfig', $args);
            $this->assertArrayHasKey('maxTokens', $args['inferenceConfig']);
            $this->assertArrayHasKey('temperature', $args['inferenceConfig']);

            // Verify unified response structure
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('content', $result);
            $this->assertArrayHasKey('metadata', $result);
            $this->assertTrue($result['success']);
            $this->assertIsString($result['content']);
            $this->assertIsArray($result['metadata']);
        });
    }

    private function createMockBedrockResponse(string $model): Result
    {
        $responseContent = 'This is a test AI response for model: '.$model;

        $responseData = [
            'output' => [
                'message' => [
                    'role' => 'assistant',
                    'content' => [
                        ['text' => $responseContent],
                    ],
                ],
            ],
            'usage' => [
                'inputTokens' => rand(50, 200),
                'outputTokens' => rand(20, 100),
            ],
            '@metadata' => [
                'headers' => [
                    'x-amzn-bedrock-invocation-latency' => rand(100, 1000),
                    'x-amzn-bedrock-input-token-count' => rand(50, 200),
                    'x-amzn-bedrock-output-token-count' => rand(20, 100),
                ],
            ],
        ];

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('toArray')->willReturn($responseData);

        return $mockResult;
    }
}

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
 * Documents the `setMockResult()` method on the anonymous `BedrockRuntimeClient`
 * subclass `createConverseSpyClient()` returns, so PHPStan can type-check calls
 * to it (the public `lastConverseArgs` property is described separately via an
 * inline object-shape PHPDoc, since object shapes cover real properties fine).
 */
interface ConverseSpyClient
{
    public function setMockResult(Result $result): void;
}

/**
 * **Feature: coaster-summary-refactor, Property 3: Unified Bedrock API Interface**.
 *
 * Tests that the BedrockService uses the same Converse API request format
 * and response parsing method regardless of the underlying model.
 */
class BedrockServiceTest extends TestCase
{
    use TestTrait;

    /**
     * **Property 3: Unified Bedrock API Interface**
     * **Validates: Requirements 3.2, 3.3, 3.4, 3.5**.
     *
     * For any supported Bedrock model (GPT OSS, GPT-5.6 Luna),
     * the BedrockService should use the same Converse API request format
     * and response parsing method regardless of the underlying model.
     */
    public function testUnifiedBedrockApiInterface(): void
    {
        $this->limitTo(10);
        $this->forAll(
            Generator\elements(['gpt-oss-120b', 'gpt-5.6-luna']), // @phpstan-ignore-line
            Generator\string(), // @phpstan-ignore-line
            Generator\choose(100, 2000), // @phpstan-ignore-line
            Generator\float(0.0, 1.0) // @phpstan-ignore-line
        )
        ->then(function (string $model, string $prompt, int $maxTokens, float $temperature): void {
            $bedrockClient = $this->createConverseSpyClient();

            $logger = $this->createMock(LoggerInterface::class);
            $service = new BedrockService($bedrockClient, $logger, 'gpt-oss-120b');

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
            // Temperature presence is model-dependent (supports_temperature) - see the
            // dedicated testTemperatureIsSentForModelsThatSupportIt/...DontSupportIt tests.

            // Verify unified response structure
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('metadata', $result);
            $this->assertTrue($result['success']);
            $this->assertArrayHasKey('content', $result);
            $this->assertIsString($result['content'] ?? '');
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
                'inputTokens' => random_int(50, 200),
                'outputTokens' => random_int(20, 100),
            ],
            'metrics' => [
                'latencyMs' => random_int(100, 1000),
            ],
            '@metadata' => [
                'headers' => [],
            ],
        ];

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('toArray')->willReturn($responseData);

        return $mockResult;
    }

    public function testReasoningEffortIsSentForModelsThatSupportIt(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-oss-120b');
        $bedrockClient->setMockResult($this->createMockBedrockResponse('gpt-oss-120b'));

        $service->invokeModel('prompt', 'gpt-oss-120b', 500, 0.5);

        $this->assertSame(
            ['reasoning_effort' => 'low'],
            $bedrockClient->lastConverseArgs['additionalModelRequestFields'] ?? null
        );
    }

    public function testReasoningEffortIsOmittedForGpt56Luna(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-5.6-luna');
        $bedrockClient->setMockResult($this->createMockBedrockResponse('gpt-5.6-luna'));

        $service->invokeModel('prompt', 'gpt-5.6-luna', 500, 0.5);

        $this->assertArrayNotHasKey('additionalModelRequestFields', $bedrockClient->lastConverseArgs);
    }

    public function testTemperatureIsOmittedForModelsThatDontSupportIt(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-5.6-luna');
        $bedrockClient->setMockResult($this->createMockBedrockResponse('gpt-5.6-luna'));

        $service->invokeModel('prompt', 'gpt-5.6-luna', 500, 0.3);

        $this->assertArrayNotHasKey('temperature', $bedrockClient->lastConverseArgs['inferenceConfig']);
    }

    public function testTemperatureIsSentForModelsThatSupportIt(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-oss-120b');
        $bedrockClient->setMockResult($this->createMockBedrockResponse('gpt-oss-120b'));

        $service->invokeModel('prompt', 'gpt-oss-120b', 500, 0.5);

        $this->assertArrayHasKey('temperature', $bedrockClient->lastConverseArgs['inferenceConfig']);
        $this->assertSame(0.5, $bedrockClient->lastConverseArgs['inferenceConfig']['temperature']);
    }

    public function testStopReasonIsCapturedInMetadata(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-oss-120b');

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('toArray')->willReturn([
            'output' => ['message' => ['content' => [['text' => '']]]],
            'usage' => ['inputTokens' => 900, 'outputTokens' => 1000],
            'stopReason' => 'max_tokens',
            '@metadata' => ['headers' => []],
        ]);
        $bedrockClient->setMockResult($mockResult);

        $result = $service->invokeModel('prompt', 'gpt-oss-120b', 1000, 0.5);

        $this->assertSame('max_tokens', $result['metadata']['stop_reason']);
    }

    public function testLatencyIsReadFromMetricsField(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-oss-120b');

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('toArray')->willReturn([
            'output' => ['message' => ['content' => [['text' => 'x']]]],
            'usage' => ['inputTokens' => 10, 'outputTokens' => 5],
            'metrics' => ['latencyMs' => 1234],
            '@metadata' => ['headers' => []],
        ]);
        $bedrockClient->setMockResult($mockResult);

        $result = $service->invokeModel('prompt', 'gpt-oss-120b', 500, 0.5);

        $this->assertSame(1234, $result['metadata']['latency_ms']);
    }

    public function testCacheTokensAreCostedAtTheirOwnRates(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-5.6-luna');

        $mockResult = $this->createMock(Result::class);
        $mockResult->method('toArray')->willReturn([
            'output' => ['message' => ['content' => [['text' => 'x']]]],
            'usage' => [
                'inputTokens' => 2,
                'outputTokens' => 503,
                'cacheReadInputTokens' => 58442,
                'cacheWriteInputTokens' => 0,
            ],
            'metrics' => ['latencyMs' => 3942],
            '@metadata' => ['headers' => []],
        ]);
        $bedrockClient->setMockResult($mockResult);

        $result = $service->invokeModel('prompt', 'gpt-5.6-luna', 500, 0.5);

        $this->assertSame(58442, $result['metadata']['cache_read_tokens']);
        $this->assertSame(0, $result['metadata']['cache_write_tokens']);

        // (2/1000)*0.0002 + (503/1000)*0.0012 + (58442/1000)*0.00002 + 0
        $expectedCost = (2 / 1000) * 0.0002 + (503 / 1000) * 0.0012 + (58442 / 1000) * 0.00002;
        $this->assertEqualsWithDelta(round($expectedCost, 6), $result['metadata']['cost_usd'], 0.0000001);

        // Cost dominated by cache reads, not the misleadingly tiny "2" input tokens -
        // this is exactly the case the old code under-reported.
        $this->assertGreaterThan((503 / 1000) * 0.0012, $result['metadata']['cost_usd']);
    }

    public function testInvokeModelReturnsClearErrorForUnknownModelKey(): void
    {
        $bedrockClient = $this->createConverseSpyClient();
        $logger = $this->createMock(LoggerInterface::class);
        $service = new BedrockService($bedrockClient, $logger, 'gpt-oss-120b');

        $result = $service->invokeModel('prompt', 'gtp-5.6-luna', 500, 0.5);

        $this->assertFalse($result['success']);

        if (!isset($result['error'])) {
            $this->fail('Expected an error message to be present');
        }

        $this->assertStringContainsString('Unknown model "gtp-5.6-luna"', $result['error']);
        $this->assertStringContainsString('gpt-oss-120b', $result['error']);
        $this->assertStringContainsString('gpt-5.6-luna', $result['error']);
        $this->assertNull($bedrockClient->lastConverseArgs);
    }

    public function testInvokeModelHasNoEnableReasoningParameter(): void
    {
        $reflection = new \ReflectionMethod(BedrockService::class, 'invokeModel');
        $paramNames = array_map(static fn (\ReflectionParameter $p) => $p->getName(), $reflection->getParameters());

        $this->assertNotContains('enableReasoning', $paramNames);
        $this->assertCount(4, $paramNames);
    }

    /** @return BedrockRuntimeClient&ConverseSpyClient&object{lastConverseArgs: ?array<string, mixed>} */
    private function createConverseSpyClient(): BedrockRuntimeClient
    {
        return new class extends BedrockRuntimeClient implements ConverseSpyClient {
            /** @var array<string, mixed>|null */
            public ?array $lastConverseArgs = null;
            private Result $mockResult;

            public function __construct()
            {
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
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\Ai;

use App\Repository\AiCallRepository;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiTask;
use App\Service\Ai\BedrockService;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BedrockServiceTest extends TestCase
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => ['verdict' => ['type' => 'string']],
        'required' => ['verdict'],
        'additionalProperties' => false,
    ];

    /** @var array<string, mixed>|null */
    private ?array $sentRequest = null;

    /** @var list<array<string, mixed>> */
    private array $recordedCalls = [];

    public function testRequestCarriesTheTaskSettingsTheInstructionsAsSystemPromptAndTheSchema(): void
    {
        $this->service(['output' => ['message' => ['content' => [['text' => '{"verdict":"ok"}']]]]])
            ->invoke(AiTask::ReviewModeration, 'Moderate.', 'The review', self::SCHEMA);

        $request = $this->sentRequest;
        $this->assertNotNull($request);
        $this->assertSame(AiTask::ReviewModeration->model()->bedrockId(), $request['modelId']);
        $this->assertSame([['text' => 'Moderate.']], $request['system']);
        $this->assertSame([['role' => 'user', 'content' => [['text' => 'The review']]]], $request['messages']);
        $this->assertSame(['maxTokens' => AiTask::ReviewModeration->maxTokens()], $request['inferenceConfig']);
        $this->assertSame(['effort' => AiTask::ReviewModeration->reasoningEffort()], $request['additionalModelRequestFields']['reasoning']);
        $this->assertTrue($request['additionalModelRequestFields']['text']['format']['strict']);
        $this->assertSame(['mode' => 'explicit'], $request['additionalModelRequestFields']['prompt_cache_options']);

        $format = $request['outputConfig']['textFormat'];
        $this->assertSame('json_schema', $format['type']);
        $this->assertSame(self::SCHEMA, json_decode($format['structure']['jsonSchema']['schema'], true));
    }

    public function testAnImageGoesAheadOfTheText(): void
    {
        $this->service(['output' => ['message' => ['content' => [['text' => '{"verdict":"ok"}']]]]])
            ->invoke(AiTask::ImageModeration, 'Moderate.', 'Moderate this photo.', self::SCHEMA, jpegBytes: 'JPEG');

        $this->assertNotNull($this->sentRequest);
        $this->assertSame([
            ['image' => ['format' => 'jpeg', 'source' => ['bytes' => 'JPEG']]],
            ['text' => 'Moderate this photo.'],
        ], $this->sentRequest['messages'][0]['content']);
    }

    public function testAModelOverrideReplacesTheTasksModel(): void
    {
        $this->service(['output' => ['message' => ['content' => [['text' => '{"verdict":"ok"}']]]]])
            ->invoke(AiTask::CoasterSummary, 'i', 'x', self::SCHEMA, model: AiModel::Gpt6Luna);

        $this->assertNotNull($this->sentRequest);
        $this->assertSame('global.openai.gpt-6-luna', $this->sentRequest['modelId']);
        $this->assertSame('gpt-6-luna', $this->recordedCalls[0]['model']);
    }

    public function testTheAnswerIsTheDecodedTextBlockAfterAnyReasoningBlock(): void
    {
        $result = $this->service([
            'output' => ['message' => ['content' => [
                ['reasoningContent' => ['reasoningText' => ['text' => 'thinking']]],
                ['text' => '{"verdict":"spam"}'],
            ]]],
            'stopReason' => 'end_turn',
        ])->invoke(AiTask::ReviewModeration, 'i', 'x', self::SCHEMA);

        $this->assertTrue($result['success']);
        $this->assertSame(['verdict' => 'spam'], $result['data']);
    }

    public function testEachSuccessfulCallIsRecordedWithItsTokensAndCost(): void
    {
        $result = $this->service([
            'output' => ['message' => ['content' => [['text' => '{"verdict":"ok"}']]]],
            'usage' => ['inputTokens' => 1_000_000, 'outputTokens' => 1_000_000, 'cacheReadInputTokens' => 1_000_000, 'cacheWriteInputTokens' => 1_000_000],
            'metrics' => ['latencyMs' => 840],
            'stopReason' => 'end_turn',
        ])->invoke(AiTask::CoasterSummary, 'i', 'x', self::SCHEMA, subjectId: 42, language: 'fr', model: AiModel::Gpt6Luna);

        // 0.10 + 0.50 + 0.01 + 0.125, one million tokens of each.
        $this->assertEqualsWithDelta(0.735, $result['metadata']['cost_usd'], 0.000001);
        $this->assertSame(840, $result['metadata']['latency_ms']);
        $this->assertSame('end_turn', $result['metadata']['stop_reason']);

        $this->assertCount(1, $this->recordedCalls);
        $call = $this->recordedCalls[0];
        $this->assertSame('coaster_summary', $call['task']);
        $this->assertSame('gpt-6-luna', $call['model']);
        $this->assertSame(42, $call['subject_id']);
        $this->assertSame('fr', $call['language']);
        $this->assertTrue($call['success']);
        $this->assertNull($call['error_code']);
        $this->assertSame(1_000_000, $call['cache_read_tokens']);
        $this->assertEqualsWithDelta(0.735, $call['cost_usd'], 0.000001);
    }

    public function testAnAnswerThatStoppedEarlyFailsWithItsStopReason(): void
    {
        $result = $this->service([
            'output' => ['message' => ['content' => [['text' => '{"verdict":"o']]]],
            'usage' => ['inputTokens' => 100, 'outputTokens' => 500],
            'stopReason' => 'max_tokens',
        ])->invoke(AiTask::ReviewModeration, 'i', 'x', self::SCHEMA);

        $this->assertFalse($result['success']);
        $this->assertSame('max_tokens', $result['error_code']);
        $this->assertFalse($this->recordedCalls[0]['success']);
        $this->assertSame('max_tokens', $this->recordedCalls[0]['error_code']);
        $this->assertSame(500, $this->recordedCalls[0]['output_tokens']);
    }

    public function testAnAnswerThatIsNotAJsonObjectFails(): void
    {
        $result = $this->service(['output' => ['message' => ['content' => [['text' => 'Sorry, I cannot help.']]]]])
            ->invoke(AiTask::ReviewModeration, 'i', 'x', self::SCHEMA);

        $this->assertFalse($result['success']);
        $this->assertSame('InvalidJson', $result['error_code']);
        $this->assertSame('InvalidJson', $this->recordedCalls[0]['error_code']);
    }

    public function testAnAwsErrorFailsIsLoggedAndRecordedWithItsCode(): void
    {
        $exception = new AwsException('Too many requests', new Command('Converse'), ['code' => 'ThrottlingException']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('AWS Bedrock API error: ThrottlingException (no status)');

        $result = $this->service($exception, $logger)->invoke(AiTask::ImageModeration, 'i', 'x', self::SCHEMA, subjectId: 7);

        $this->assertFalse($result['success']);
        $this->assertSame('ThrottlingException', $result['error_code']);
        $this->assertSame(7, $this->recordedCalls[0]['subject_id']);
        $this->assertSame('ThrottlingException', $this->recordedCalls[0]['error_code']);
        $this->assertSame(0.0, $this->recordedCalls[0]['cost_usd']);
    }

    public function testAFailureToRecordTheCallKeepsTheAnswer(): void
    {
        $client = $this->createMock(BedrockRuntimeClient::class);
        $client->method('__call')->willReturn(new Result(['output' => ['message' => ['content' => [['text' => '{"verdict":"ok"}']]]]]));

        $repository = $this->createMock(AiCallRepository::class);
        $repository->method('record')->willThrowException(new \RuntimeException('table missing'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Could not record the AI call');

        $result = new BedrockService($client, $repository, $logger)->invoke(AiTask::ReviewModeration, 'i', 'x', self::SCHEMA);

        $this->assertTrue($result['success']);
    }

    public function testCostUsesEachModelsOwnRates(): void
    {
        $this->assertEqualsWithDelta(1.40, AiModel::Gpt56Luna->costUsd(1_000_000, 1_000_000), 0.000001);
        $this->assertEqualsWithDelta(0.60, AiModel::Gpt6Luna->costUsd(1_000_000, 1_000_000), 0.000001);
    }

    /** @param array<string, mixed>|AwsException $response */
    private function service(array|AwsException $response, ?LoggerInterface $logger = null): BedrockService
    {
        $client = $this->createMock(BedrockRuntimeClient::class);
        $client->method('__call')->willReturnCallback(function (string $name, array $args) use ($response): Result {
            $this->assertSame('converse', $name);
            $this->sentRequest = $args[0];

            if ($response instanceof AwsException) {
                throw $response;
            }

            return new Result($response);
        });

        $repository = $this->createMock(AiCallRepository::class);
        $repository->method('record')->willReturnCallback(function (array $call): void {
            $this->recordedCalls[] = $call;
        });

        return new BedrockService($client, $repository, $logger ?? $this->createMock(LoggerInterface::class));
    }
}

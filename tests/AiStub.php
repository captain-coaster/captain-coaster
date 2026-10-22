<?php

declare(strict_types=1);

namespace App\Tests;

use App\Service\Ai\AiCallFailed;
use App\Service\Ai\AiModel;
use App\Service\Ai\AiResult;
use App\Service\Ai\AiTask;

/**
 * What a mocked AiClient returns or throws.
 */
final class AiStub
{
    /** @param array<string, mixed> $data */
    public static function result(array $data, AiModel $model = AiModel::Gpt6Luna, float $costUsd = 0.0): AiResult
    {
        return new AiResult($data, $model, 100, 50, 0, 0, $costUsd, 500);
    }

    public static function failure(AiTask $task, string $errorCode = 'ThrottlingException'): AiCallFailed
    {
        return new AiCallFailed($task, $errorCode, 'Throttled');
    }
}

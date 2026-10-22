<?php

declare(strict_types=1);

namespace App\Service\Ai;

/**
 * The model gave no usable answer: an AWS error, an answer cut short, or one that is not JSON.
 * Already logged and recorded in ai_call by AiClient.
 */
final class AiCallFailed extends \RuntimeException
{
    public function __construct(
        public readonly AiTask $task,
        public readonly string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('%s call failed (%s): %s', $task->value, $errorCode, $message), 0, $previous);
    }
}

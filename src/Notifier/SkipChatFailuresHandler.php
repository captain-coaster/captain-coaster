<?php

declare(strict_types=1);

namespace App\Notifier;

use Monolog\Handler\HandlerWrapper;
use Monolog\LogRecord;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Keeps the Messenger failures of a Discord message away from the Discord notifier.
 *
 * Chat messages go through the async transport. When Discord is down, the worker logs the failed
 * ChatMessage as an error; notifying it would queue another ChatMessage, which fails the same way,
 * and so on until Discord comes back. Those records still reach the log file and the failure
 * transport; every other error, from the worker or a request, is notified as before.
 */
final class SkipChatFailuresHandler extends HandlerWrapper
{
    public function isHandling(LogRecord $record): bool
    {
        return !self::isChatFailure($record) && parent::isHandling($record);
    }

    public function handle(LogRecord $record): bool
    {
        if (self::isChatFailure($record)) {
            return false;
        }

        return parent::handle($record);
    }

    public function handleBatch(array $records): void
    {
        $records = array_values(array_filter($records, static fn (LogRecord $record): bool => !self::isChatFailure($record)));
        if ([] !== $records) {
            parent::handleBatch($records);
        }
    }

    private static function isChatFailure(LogRecord $record): bool
    {
        return 'messenger' === $record->channel && ChatMessage::class === ($record->context['class'] ?? null);
    }
}

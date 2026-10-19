<?php

declare(strict_types=1);

namespace App\Notifier;

use Monolog\Handler\HandlerInterface;
use Monolog\Handler\HandlerWrapper;
use Monolog\LogRecord;
use Symfony\Bridge\Monolog\Handler\NotifierHandler;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Wraps the Monolog handler that posts errors to Discord, so that a Discord message failing to send is not itself
 * posted to Discord.
 *
 * Chat messages go through the async transport. When Discord is down, the worker logs the failed
 * ChatMessage as an error; notifying it would queue another ChatMessage, which fails the same way,
 * and so on until Discord comes back. Those records still reach the log file and the failure
 * transport; every other error, from the worker or a request, is notified as before.
 */
#[AsDecorator(NotifierHandler::class)]
final class NotifierLoopGuard extends HandlerWrapper
{
    public function __construct(#[AutowireDecorated] HandlerInterface $handler)
    {
        parent::__construct($handler);
    }

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

<?php

declare(strict_types=1);

namespace App\Tests\Notifier;

use App\Message\SendNotificationEmailMessage;
use App\Notifier\NotifierLoopGuard;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Message\ChatMessage;

class NotifierLoopGuardTest extends TestCase
{
    public function testSkipsAFailedChatMessage(): void
    {
        $inner = new TestHandler();
        $handler = new NotifierLoopGuard($inner);

        $this->assertFalse($handler->isHandling($this->record('messenger', ChatMessage::class)));
        $handler->handle($this->record('messenger', ChatMessage::class));

        $this->assertFalse($inner->hasRecords(Level::Critical));
    }

    public function testPassesAnotherFailedMessage(): void
    {
        $inner = new TestHandler();

        new NotifierLoopGuard($inner)->handle($this->record('messenger', SendNotificationEmailMessage::class));

        $this->assertTrue($inner->hasCriticalThatContains('Error thrown while handling message'));
    }

    public function testPassesAnErrorFromAnotherChannel(): void
    {
        $inner = new TestHandler();

        new NotifierLoopGuard($inner)->handle($this->record('request', ChatMessage::class));

        $this->assertTrue($inner->hasCriticalRecords());
    }

    public function testFiltersABatch(): void
    {
        $inner = new TestHandler();

        new NotifierLoopGuard($inner)->handleBatch([
            $this->record('messenger', ChatMessage::class),
            $this->record('messenger', SendNotificationEmailMessage::class),
        ]);

        $this->assertCount(1, $inner->getRecords());
        $this->assertSame(SendNotificationEmailMessage::class, $inner->getRecords()[0]->context['class']);
    }

    public function testLeavesTheInnerHandlerAloneForABatchOfChatFailures(): void
    {
        $inner = new TestHandler();

        new NotifierLoopGuard($inner)->handleBatch([$this->record('messenger', ChatMessage::class)]);

        $this->assertFalse($inner->hasRecords(Level::Critical));
    }

    private function record(string $channel, string $class): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: $channel,
            level: Level::Critical,
            message: 'Error thrown while handling message {class}. Removing from transport after {retryCount} retries.',
            context: ['class' => $class, 'retryCount' => 3],
        );
    }
}

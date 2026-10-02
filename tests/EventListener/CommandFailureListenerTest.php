<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\CommandFailureListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class CommandFailureListenerTest extends TestCase
{
    private ChatterInterface&MockObject $chatter;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->chatter = $this->createMock(ChatterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function listener(string $environment = 'prod'): CommandFailureListener
    {
        return new CommandFailureListener($this->chatter, $this->logger, $environment);
    }

    private function input(bool $interactive = false): ArrayInput
    {
        $input = new ArrayInput([]);
        $input->setInteractive($interactive);

        return $input;
    }

    private function terminate(int $exitCode, bool $interactive = false, Command $command = new Command('sitemap:update')): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent($command, $this->input($interactive), new NullOutput(), $exitCode);
    }

    public function testAFailedCronCommandIsReportedWithItsError(): void
    {
        $this->chatter->expects($this->once())->method('send')->with($this->callback(
            static fn (ChatMessage $message): bool => 'discord_log' === $message->getTransport()
                && str_contains($message->getSubject(), '`sitemap:update` failed (exit code 1)')
                && str_contains($message->getSubject(), 'database is down')
        ));

        $listener = $this->listener();
        $command = new Command('sitemap:update');
        $listener->onError(new ConsoleErrorEvent($this->input(), new NullOutput(), new \RuntimeException('database is down'), $command));
        $listener->onTerminate($this->terminate(1, command: $command));
    }

    public function testAFailureWithoutExceptionIsReportedToo(): void
    {
        $this->chatter->expects($this->once())->method('send')->with($this->callback(
            static fn (ChatMessage $message): bool => str_ends_with($message->getSubject(), 'failed (exit code 1)')
        ));

        $this->listener()->onTerminate($this->terminate(Command::FAILURE));
    }

    public function testALongErrorIsTruncated(): void
    {
        $this->chatter->expects($this->once())->method('send')->with($this->callback(
            static fn (ChatMessage $message): bool => mb_strlen($message->getSubject()) < 600
        ));

        $listener = $this->listener();
        $listener->onError(new ConsoleErrorEvent($this->input(), new NullOutput(), new \RuntimeException(str_repeat('x', 5000))));
        $listener->onTerminate($this->terminate(1));
    }

    public function testNothingIsSentOnSuccessOutsideProdOrInATerminal(): void
    {
        $this->chatter->expects($this->never())->method('send');

        $this->listener()->onTerminate($this->terminate(Command::SUCCESS));
        $this->listener('dev')->onTerminate($this->terminate(1));
        $this->listener()->onTerminate($this->terminate(1, interactive: true));
    }

    public function testAnEarlierErrorIsNotAttachedToALaterRun(): void
    {
        $this->chatter->expects($this->once())->method('send')->with($this->callback(
            static fn (ChatMessage $message): bool => !str_contains($message->getSubject(), 'first error')
        ));

        $listener = $this->listener();
        $listener->onError(new ConsoleErrorEvent($this->input(), new NullOutput(), new \RuntimeException('first error')));
        $listener->onTerminate($this->terminate(Command::SUCCESS));
        $listener->onTerminate($this->terminate(Command::FAILURE));
    }

    public function testADiscordOutageIsLoggedAndDoesNotThrow(): void
    {
        $this->chatter->method('send')->willThrowException(new \RuntimeException('discord is down'));
        $this->logger->expects($this->once())->method('error');

        $this->listener()->onTerminate($this->terminate(1));
    }
}

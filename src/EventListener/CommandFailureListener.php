<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Posts to the Discord log channel when a console command fails in production without anyone
 * watching: the cron jobs only write to a log file nobody reads, so a failing one (sitemap,
 * ranking, summaries...) went unnoticed.
 *
 * Only non-interactive runs (cron, systemd) notify; a command typed in a terminal already
 * shows its error to whoever ran it.
 */
final class CommandFailureListener
{
    private const int MAX_ERROR_LENGTH = 500;

    private ?string $error = null;

    public function __construct(
        // Lazy: this listener is built for every console command, and the chatter (with its
        // transport DSNs, empty outside production) must only be built to send a message.
        #[Autowire(lazy: true)]
        private readonly ChatterInterface $chatter,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    #[AsEventListener(event: ConsoleEvents::ERROR)]
    public function onError(ConsoleErrorEvent $event): void
    {
        $this->error = $event->getError()->getMessage();
    }

    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $error = $this->error;
        $this->error = null;

        $command = $event->getCommand();
        if (0 === $event->getExitCode() || null === $command || 'prod' !== $this->environment || $event->getInput()->isInteractive()) {
            return;
        }

        $text = \sprintf('⚠️ Command `%s` failed (exit code %d)', $command->getName(), $event->getExitCode());
        if (null !== $error && '' !== $error) {
            $text .= ': '.mb_strimwidth($error, 0, self::MAX_ERROR_LENGTH, '…');
        }

        try {
            $this->chatter->send(new ChatMessage($text)->transport('discord_log'));
        } catch (\Throwable $e) {
            // The command already failed; a Discord outage must not hide its own error.
            $this->logger->error('Could not report a failed command to Discord', ['exception' => $e]);
        }
    }
}

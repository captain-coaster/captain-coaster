<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * SendRankingNotifCommand writes one row per user per run and nothing ever
 * removed them, so the table grew without bound. Read notifications are never
 * displayed again — only their row cost remains.
 */
#[AsCommand(
    name: 'app:notification:purge',
    description: 'Delete read notifications older than the retention window'
)]
class NotificationPurgeCommand extends Command
{
    private const int DEFAULT_RETENTION_DAYS = 90;

    public function __construct(private readonly NotificationRepository $notificationRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Retention window in days', self::DEFAULT_RETENTION_DAYS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without actually doing it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $io->error('--days must be a positive integer.');

            return Command::INVALID;
        }

        $before = new \DateTimeImmutable(\sprintf('-%d days', $days));

        if ($input->getOption('dry-run')) {
            $io->warning('DRY RUN - No changes will be made.');
            $io->success(\sprintf('%d read notifications older than %s would be deleted.', $this->notificationRepository->countReadOlderThan($before), $before->format('Y-m-d')));

            return Command::SUCCESS;
        }

        $deleted = $this->notificationRepository->deleteReadOlderThan($before);

        $io->success(\sprintf('Deleted %d read notifications older than %s.', $deleted, $before->format('Y-m-d')));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NotificationRecipientRepository;
use App\Repository\NotificationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Without this, notification rows accumulate without bound: a read
 * notification is never displayed again, and a dormant account's unread
 * broadcast notifications (e.g. monthly ranking updates) would otherwise
 * never age out at all.
 */
#[AsCommand(
    name: 'app:notification:purge',
    description: 'Delete notifications older than their retention window'
)]
class NotificationPurgeCommand extends Command
{
    private const int READ_RETENTION_DAYS = 365;
    private const int UNREAD_RETENTION_DAYS = 365;

    public function __construct(
        private readonly NotificationRecipientRepository $notificationRecipientRepository,
        private readonly NotificationRepository $notificationRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('read-days', null, InputOption::VALUE_REQUIRED, 'Retention window in days for read notifications', self::READ_RETENTION_DAYS)
            ->addOption('unread-days', null, InputOption::VALUE_REQUIRED, 'Retention window in days for unread notifications', self::UNREAD_RETENTION_DAYS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without actually doing it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $readDays = (int) $input->getOption('read-days');
        $unreadDays = (int) $input->getOption('unread-days');
        if ($readDays < 1 || $unreadDays < 1) {
            $io->error('--read-days and --unread-days must be positive integers.');

            return Command::INVALID;
        }

        $readBefore = new \DateTimeImmutable(\sprintf('-%d days', $readDays));
        $unreadBefore = new \DateTimeImmutable(\sprintf('-%d days', $unreadDays));
        // Orphans can only exist among rows old enough that every recipient has
        // already aged out, so the older of the two cutoffs is always safe here.
        $orphanBefore = min($readBefore, $unreadBefore);

        if ($input->getOption('dry-run')) {
            $io->warning('DRY RUN - No changes will be made.');
            $io->success(\sprintf(
                '%d read notifications older than %s, %d unread notifications older than %s, and %d orphaned notification(s) would be deleted.',
                $this->notificationRecipientRepository->countReadOlderThan($readBefore),
                $readBefore->format('Y-m-d'),
                $this->notificationRecipientRepository->countUnreadOlderThan($unreadBefore),
                $unreadBefore->format('Y-m-d'),
                $this->notificationRepository->countOrphanedBefore($orphanBefore)
            ));

            return Command::SUCCESS;
        }

        $deletedRead = $this->notificationRecipientRepository->deleteReadOlderThan($readBefore);
        $deletedUnread = $this->notificationRecipientRepository->deleteUnreadOlderThan($unreadBefore);
        $deletedOrphaned = $this->notificationRepository->deleteOrphanedBefore($orphanBefore);

        $io->success(\sprintf(
            'Deleted %d read notifications older than %s, %d unread notifications older than %s, and %d orphaned notification(s).',
            $deletedRead,
            $readBefore->format('Y-m-d'),
            $deletedUnread,
            $unreadBefore->format('Y-m-d'),
            $deletedOrphaned
        ));

        return Command::SUCCESS;
    }
}

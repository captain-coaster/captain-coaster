<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\AccountDeletionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:delete-expired-accounts',
    description: 'Permanently delete accounts scheduled for deletion over 1 month ago, and purge data from long-banned users'
)]
class DeleteExpiredAccountsCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AccountDeletionService $accountDeletionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without actually doing it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('DRY RUN - No changes will be made.');
        }

        $this->processScheduledDeletions($io, $dryRun);
        $this->processBannedUsers($io, $dryRun);

        return Command::SUCCESS;
    }

    private function processScheduledDeletions(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Processing scheduled account deletions');

        $oneMonthAgo = new \DateTime('-1 month');
        $users = $this->userRepository->findUsersScheduledForDeletion($oneMonthAgo);

        if (0 === \count($users)) {
            $io->success('No accounts to delete.');

            return;
        }

        $io->note(\sprintf('Found %d account(s) to permanently delete.', \count($users)));

        foreach ($users as $user) {
            if ($dryRun) {
                $io->text(\sprintf('[DRY RUN] Would delete account: %s (ID: %d)', $user->getEmail(), $user->getId()));
            } else {
                $io->text(\sprintf('Deleting account: %s (ID: %d)', $user->getEmail(), $user->getId()));
                $this->accountDeletionService->permanentlyDeleteAccount($user);
            }
        }

        if ($dryRun) {
            $io->success(\sprintf('[DRY RUN] Would delete %d account(s).', \count($users)));
        } else {
            $io->success(\sprintf('Successfully deleted %d account(s).', \count($users)));
        }
    }

    private function processBannedUsers(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Processing banned users data purge');

        $oneMonthAgo = new \DateTime('-1 month');
        $users = $this->userRepository->findUsersBannedBefore($oneMonthAgo);

        if (0 === \count($users)) {
            $io->success('No banned users to purge.');

            return;
        }

        $totalUsers = \count($users);
        $io->note(\sprintf('Found %d banned user(s) to purge data from.', $totalUsers));

        // Collect user IDs first since we'll clear the EntityManager after each purge
        $userIds = array_map(fn ($user) => $user->getId(), $users);
        $processedCount = 0;

        foreach ($userIds as $userId) {
            $user = $this->userRepository->find($userId);
            if (null === $user) {
                continue;
            }

            if ($dryRun) {
                $io->text(\sprintf('[DRY RUN] Would purge data for: %s (ID: %d)', $user->getEmail(), $user->getId()));
            } else {
                $io->text(\sprintf('Purging data for: %s (ID: %d)', $user->getEmail(), $user->getId()));
                $this->accountDeletionService->purgeUserData($user);
            }
            ++$processedCount;
        }

        if ($dryRun) {
            $io->success(\sprintf('[DRY RUN] Would purge data for %d banned user(s).', $processedCount));
        } else {
            $io->success(\sprintf('Successfully purged data for %d banned user(s).', $processedCount));
        }
    }
}

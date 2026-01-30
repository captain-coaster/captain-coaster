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
    description: 'Permanently delete accounts scheduled for deletion over 1 month ago'
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
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show accounts that would be deleted without actually deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $oneMonthAgo = new \DateTime('-1 month');
        $users = $this->userRepository->findUsersScheduledForDeletion($oneMonthAgo);

        if (0 === \count($users)) {
            $io->success('No accounts to delete.');

            return Command::SUCCESS;
        }

        $io->note(\sprintf('Found %d account(s) to permanently delete.', \count($users)));

        if ($dryRun) {
            $io->warning('DRY RUN - No accounts will be deleted.');
        }

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

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\NotificationType;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-off: tells the users who embed their banner elsewhere that banners are
 * retired. The message key must already be deployed in the main app, which
 * resolves it at display time. Never merged, like the rename.
 */
#[AsCommand(
    name: 'app:banner:notify-retirement',
    description: 'Send the banner retirement notice to the given user ids',
    hidden: true,
)]
class BannerRetirementNotifyCommand extends Command
{
    private const string MESSAGE = 'notif.announcement.bannerRetired';

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::REQUIRED, 'Comma-separated user ids')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the recipients, send nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        /** @var string $idsArgument */
        $idsArgument = $input->getArgument('ids');
        $ids = array_values(array_unique(array_filter(array_map(intval(...), explode(',', $idsArgument)))));

        $sent = 0;
        $missing = [];
        foreach ($ids as $id) {
            $user = $this->userRepository->find($id);
            if (null === $user || !$user->isEnabled()) {
                $missing[] = $id;

                continue;
            }

            $io->writeln(\sprintf('#%d %s (email notification %s)', $id, $user, $user->isEmailNotification() ? 'on' : 'off'));
            if (!$dryRun) {
                $this->notificationService->send($user, NotificationType::Announcement, self::MESSAGE);
            }
            ++$sent;
        }

        if ([] !== $missing) {
            $io->warning(\sprintf('Skipped (not found or disabled): %s', implode(', ', $missing)));
        }
        $io->success(\sprintf('%s %d of %d users.', $dryRun ? 'Would notify' : 'Notified', $sent, \count($ids)));

        return Command::SUCCESS;
    }
}

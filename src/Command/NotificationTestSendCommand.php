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
 * Sends a real notification to exactly one user via {@see NotificationService::send()}
 * — never the broadcast path — so the full pipeline (rendering, content
 * dedup, email gating, async dispatch) can be verified against a single
 * account without touching anyone else. Useful in particular for Ranking,
 * which otherwise only fires for real on the 1st of the month via
 * ranking:update and always broadcasts to every user.
 */
#[AsCommand(
    name: 'app:notification:test-send',
    description: 'Send a test notification to one user only — never broadcasts',
    hidden: true,
)]
class NotificationTestSendCommand extends Command
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('userId', InputArgument::REQUIRED, 'User ID to send the test notification to')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'ranking|badge|announcement', 'ranking')
            ->addOption('coaster', null, InputOption::VALUE_REQUIRED, 'Highlighted coaster name (ranking type only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var int $userId */
        $userId = $input->getArgument('userId');
        $user = $this->userRepository->find($userId);
        if (null === $user) {
            $io->error(\sprintf('User #%d not found.', $userId));

            return Command::FAILURE;
        }

        /** @var string $typeOption */
        $typeOption = $input->getOption('type');
        $type = NotificationType::tryFrom($typeOption);
        if (null === $type) {
            $io->error('--type must be one of: ranking, badge, announcement');

            return Command::FAILURE;
        }

        /** @var ?string $coaster */
        $coaster = $input->getOption('coaster');
        [$message, $parameter] = match ($type) {
            NotificationType::Ranking => [null !== $coaster ? 'notif.ranking.messageWithNewCoaster' : 'notif.ranking.message', $coaster],
            NotificationType::Badge => ['notif.badge.message', 'badge.rating1'],
            NotificationType::Announcement => ['notif.announcement.emailDefaultChanged', null],
        };

        $this->notificationService->send($user, $type, $message, $parameter);

        $io->success(\sprintf('Test %s notification sent to user #%d (%s) only.', $type->value, $user->getId(), $user->getEmail()));

        return Command::SUCCESS;
    }
}

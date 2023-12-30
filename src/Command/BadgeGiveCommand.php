<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class BadgeGiveCommand extends Command
{
    protected static $defaultName = 'badge:give';

    public function __construct(private readonly BadgeService $badgeService, private readonly UserRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Give badges to users')
            ->addArgument('user', InputArgument::OPTIONAL, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = [];
        $stopwatch = new Stopwatch();
        $stopwatch->start('badge');
        $output->writeln('Start giving badges.');

        $userId = $input->getArgument('user');

        if (null !== $userId) {
            $users[] = $this->userRepository->findOneBy(['id' => $userId]);
        } else {
            $users = $this->userRepository->getUsersWithRecentRatingOrTopUpdate();
        }

        /** @var User $user */
        foreach ($users as $user) {
            $output->writeln("Checking $user...");
            $this->badgeService->give($user);
        }

        $output->writeln('End of command.');
        $output->writeln((string) $stopwatch->stop('badge'));

        return 0;
    }
}

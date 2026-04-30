<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\BannerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'banner:generate',
    description: 'Generate SVG banners for users with recent rating or top changes',
)]
class BannerGenerateCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly BannerService $bannerService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Regenerate banners for all users (migration)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('banner');

        $isAll = $input->getOption('all');

        if ($isAll) {
            $output->writeln('Generating banners for ALL users...');
            $users = $this->userRepository->findAll();
        } else {
            $output->writeln('Generating banners for recently updated users...');
            $users = $this->userRepository->getUsersWithRecentRatingOrTopUpdate();
        }

        $generated = 0;

        /** @var User $user */
        foreach ($users as $user) {
            $output->writeln(\sprintf('Generating banner for %s (ID: %d)...', $user, $user->getId()));

            try {
                $this->bannerService->generateAndUpload($user);
                ++$generated;
            } catch (\Throwable $e) {
                $output->writeln(\sprintf('<error>Failed for user %d: %s</error>', $user->getId(), $e->getMessage()));
            }
        }

        $output->writeln(\sprintf('Generated %d banners.', $generated));
        $output->writeln((string) $stopwatch->stop('banner'));

        return Command::SUCCESS;
    }
}

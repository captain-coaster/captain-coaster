<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\BannerMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BannerMakerCommand extends Command
{
    protected static $defaultName = 'banner:make';

    public function __construct(private readonly BannerMaker $bannerMakerService, private readonly UserRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Generate banners for users')
            ->addArgument('user', InputArgument::OPTIONAL, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = [];
        $output->writeln('Start making banners.');

        $userId = $input->getArgument('user');

        if (null !== $userId) {
            $users[] = $this->userRepository->findOneBy(['id' => $userId]);
        } else {
            $users = $this->userRepository->findAll();
        }

        foreach ($users as $user) {
            $this->bannerMakerService->makeBanner($user);
        }

        $output->writeln('End.');

        return 0;
    }
}

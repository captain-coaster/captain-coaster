<?php

namespace App\Command;

use App\Entity\User;
use App\Service\BannerMaker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BannerMakerCommand extends Command
{
    /**
     * @var BannerMaker
     */
    private $bannerMakerService;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(BannerMaker $bannerMakerService, EntityManagerInterface $em)
    {
        parent::__construct();
        $this->bannerMakerService = $bannerMakerService;
        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setName('banner:make')
            ->setDescription('Generate banners for users')
            ->addArgument('user', InputArgument::OPTIONAL, 'User ID');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Start making banners.');

        $userId = $input->getArgument('user');

        if (!is_null($userId)) {
            $users[] = $this->em->getRepository(User::class)->findOneBy(['id' => $userId]);
        } else {
            $users = $this->em->getRepository(User::class)->findAll();
        }

        foreach ($users as $user) {
            $this->bannerMakerService->makeBanner($user);
        }

        $output->writeln('End.');
    }
}

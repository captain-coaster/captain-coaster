<?php

namespace App\Command;

use App\Service\BannerMaker;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BannerMakerCommand extends ContainerAwareCommand
{

    private $bannerMakerService;

    public function __construct(BannerMaker $bannerMakerService)
    {
        parent::__construct();

        $this->bannerMakerService = $bannerMakerService;
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
            $users[] = $this->getContainer()
                ->get('doctrine.orm.entity_manager')
                ->getRepository('App:User')
                ->findOneBy(['id' => $userId]);
        } else {
            $users = $this->getContainer()
                ->get('doctrine.orm.entity_manager')
                ->getRepository('App:User')
                ->findAll();
        }

        foreach ($users as $user) {
            $this->bannerMakerService->makeBanner($user);
        }

        $output->writeln('End.');
    }
}

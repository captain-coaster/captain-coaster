<?php

namespace BddBundle\Command;

use BddBundle\Entity\User;
use BddBundle\Service\BadgeService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class BadgeGiveCommand extends ContainerAwareCommand
{
    /**
     * @var BadgeService
     */
    private $badgeService;

    /**
     * BadgeGiveCommand constructor.
     * @param BadgeService $badgeService
     */
    public function __construct(BadgeService $badgeService)
    {
        parent::__construct();

        $this->badgeService = $badgeService;
    }

    protected function configure()
    {
        $this
            ->setName('badge:give')
            ->setDescription('Give badges to users')
            ->addArgument('user', InputArgument::OPTIONAL, 'User ID');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('badge');
        $output->writeln('Start giving badges.');

        $userId = $input->getArgument('user');

        if (!is_null($userId)) {
            $users[] = $this->getContainer()->get('doctrine.orm.entity_manager')->getRepository('BddBundle:User')
                ->findOneBy(['id' => $userId]);
        } else {
            $users = $this->getContainer()->get('doctrine.orm.entity_manager')->getRepository('BddBundle:User')
                ->getUsersWithRecentRatingOrTopUpdate();
        }

        /** @var User $user */
        foreach ($users as $user) {
            $output->writeln('Checking '.$user->getUsername().'...');
            $this->badgeService->give($user);
        }

        $output->writeln('End of command.');
        $event = $stopwatch->stop('badge');
        $output->writeln(($event->getDuration() / 1000).' s');
        $output->writeln(($event->getMemory() / 1000 / 1000).' mo');
    }
}

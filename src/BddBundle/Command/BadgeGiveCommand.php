<?php

namespace BddBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class BadgeGiveCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('badge:give')
            ->setDescription('Give badges to users')
            ->addArgument('user', InputArgument::OPTIONAL, 'Argument description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('badge');
        $output->writeln('Start giving badges.');

        $userId = $input->getArgument('user');

        if (!is_null($userId)) {
            $users[] = $this->getContainer()
                ->get('doctrine.orm.entity_manager')
                ->getRepository('BddBundle:User')
                ->findOneBy(['id' => $userId]);
        } else {
            $users = $this->getContainer()
                ->get('doctrine.orm.entity_manager')
                ->getRepository('BddBundle:User')
                ->findAll();
        }

        $badgeService = $this->getContainer()->get('BddBundle\Service\BadgeService');

        foreach ($users as $user) {
            $badgeService->give($user);
        }

        $output->writeln('End of command.');
        $event = $stopwatch->stop('badge');
        $output->writeln(($event->getDuration()/1000).' s');
        $output->writeln(($event->getMemory()/1000/1000).' mo');
    }
}

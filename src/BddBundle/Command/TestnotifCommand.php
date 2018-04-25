<?php

namespace BddBundle\Command;

use BddBundle\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestnotifCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('testnotif')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->getContainer()
            ->get('doctrine.orm.default_entity_manager')
            ->getRepository('BddBundle:User')
            ->findOneBy(['id' => 1]);

        $this->getContainer()
            ->get('BddBundle\Service\NotificationService')
            ->send(
                $user,
                'notif.ranking.message',
                null,
                NotificationService::NOTIF_RANKING
        );
    }

}

<?php

namespace BddBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RegenerateUserSlugCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('RegenerateUserSlug')
            ->setDescription('...')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Doctrine\ORM\ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()
            ->get('doctrine.orm.default_entity_manager');
        $users = $em
            ->getRepository('BddBundle:User')
            ->findAll();

        foreach ($users as $user) {
            $user->setSlug(null);
            $em->persist($user);
        }

        $em->flush();
    }

}

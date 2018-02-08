<?php

namespace BddBundle\Command;

use BddBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AvatarCleanCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var Client
     */
    private $client;

    /**
     * AvatarCleanCommand constructor.
     * @param EntityManager $em
     * @param Client        $client
     */
    public function __construct(EntityManager $em, Client $client)
    {
        parent::__construct();

        $this->em = $em;
        $this->client = $client;
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('avatar:clean')
            ->setDescription('...')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Doctrine\ORM\ORMException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $users = $this->em
            ->getRepository('BddBundle:User')
            ->findAll();

        /** @var User $user */
        foreach ($users as $user) {
            if (!is_null($user->getProfilePicture())) {
                try {
                    $this->client->get($user->getProfilePicture());
                } catch (RequestException $e) {
                    $user->setProfilePicture(null);
                    $this->em->persist($user);
                }
            }
        }

        $this->em->flush();

        $output->writeln('End.');
    }
}

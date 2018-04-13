<?php

namespace BddBundle\Command;

use BddBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AvatarCleanCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * AvatarCleanCommand constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('avatar:clean')
            ->setDescription('Cleanup dead link to Facebook or Google avatars.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
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
                    $request = $this->getContainer()->get('httplug.message_factory')
                        ->createRequest('GET', $user->getProfilePicture());
                    $this->getContainer()->get('httplug.client.avatar')->sendRequest($request);
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

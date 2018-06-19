<?php

namespace BddBundle\Command;

use BddBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AvatarCleanCommand extends ContainerAwareCommand
{
    const API_FB_PICTURE = 'https://graph.facebook.com/v2.12/%d/picture';

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

    protected function configure()
    {
        $this
            ->setName('avatar:clean')
            ->setDescription('Update Facebook avatar and cleanup dead avatars.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Http\Client\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $users = $this->em
            ->getRepository('BddBundle:User')
            ->findAll();

        /** @var User $user */
        foreach ($users as $user) {
            $this->updateFacebookAvatar($user);
            $this->removeDeadAvatar($user);
        }

        $this->em->flush();

        $output->writeln('End.');
    }

    /**
     * @param User $user
     * @throws \Http\Client\Exception
     */
    private function updateFacebookAvatar(User $user)
    {
        if (is_null($user->getFacebookId())) {
            return;
        }

        try {
            $request = $this
                ->getContainer()
                ->get('httplug.message_factory')
                ->createRequest('GET', sprintf(self::API_FB_PICTURE, $user->getFacebookId()));
            $response = $this
                ->getContainer()
                ->get('httplug.client.avatar')
                ->sendRequest($request);

            if ($response->getStatusCode() === 302) {
                if (count($response->getHeader('Location')) === 1) {
                    $user->setProfilePicture($response->getHeader('Location')[0]);
                    $this->em->persist($user);
                }
            }
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * @param User $user
     * @throws \Http\Client\Exception
     */
    private function removeDeadAvatar(User $user)
    {
        if (is_null($user->getProfilePicture())) {
            return;
        }

        try {
            $request = $this->getContainer()
                ->get('httplug.message_factory')
                ->createRequest('GET', $user->getProfilePicture());
            $response = $this->getContainer()
                ->get('httplug.client.avatar')
                ->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                $user->setProfilePicture(null);
                $this->em->persist($user);
            }
        } catch (\Exception $e) {
            return;
        }
    }
}

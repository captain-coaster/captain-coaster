<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class AvatarCleanCommand extends Command
{
    const API_FB_PICTURE = 'https://graph.facebook.com/v7.0/%d/picture';

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var HttpClient
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $factory;

    /**
     * AvatarCleanCommand constructor.
     * @param EntityManagerInterface $em
     * @param HttpClient $client
     * @param MessageFactory $factory
     */
    public function __construct(EntityManagerInterface $em, HttpClient $client, MessageFactory $factory)
    {
        parent::__construct();
        $this->em = $em;
        $this->client = $client;
        $this->factory = $factory;
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
        $stopwatch = new Stopwatch();
        $stopwatch->start('avatar');
        $output->writeln('Cleaning avatars...');

        $users = $this->em
            ->getRepository('App:User')
            ->findAll();

        /** @var User $user */
        foreach ($users as $user) {
            $this->updateFacebookAvatar($user, $output);
            $this->removeDeadAvatar($user, $output);
        }

        $this->em->flush();

        $output->writeln('End.');
        $output->writeln((string)$stopwatch->stop('avatar'));
    }

    /**
     * @param User $user
     * @param OutputInterface $output
     * @throws \Http\Client\Exception
     */
    private function updateFacebookAvatar(User $user, OutputInterface $output)
    {
        if (is_null($user->getFacebookId())) {
            return;
        }

        try {
            $request = $this
                ->factory
                ->createRequest('GET', sprintf(self::API_FB_PICTURE, $user->getFacebookId()));
            $response = $this
                ->client
                ->sendRequest($request);


            if ($response->getStatusCode() === 302) {
                if ($response->hasHeader('Location')) {
                    $user->setProfilePicture($response->getHeader('Location')[0]);
                    $this->em->persist($user);

                    $output->writeln("Updating $user");
                }
            }
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * @param User $user
     * @param OutputInterface $output
     * @throws \Http\Client\Exception
     */
    private function removeDeadAvatar(User $user, OutputInterface $output)
    {
        if (is_null($user->getProfilePicture())) {
            return;
        }

        try {
            $request = $this
                ->factory
                ->createRequest('GET', $user->getProfilePicture());
            $response = $this
                ->client
                ->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                $user->setProfilePicture(null);
                $this->em->persist($user);

                $output->writeln("Removing avatar for $user");
            }
        } catch (\Exception $e) {
            return;
        }
    }
}

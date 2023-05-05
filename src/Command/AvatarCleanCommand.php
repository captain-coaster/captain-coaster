<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AvatarCleanCommand extends Command
{
    public $repository;
    protected static $defaultName = 'avatar:clean';
    final public const API_FB_PICTURE = 'https://graph.facebook.com/v7.0/%d/picture';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $client,
        private readonly \App\Repository\UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Update Facebook avatar and cleanup dead avatars.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('avatar');
        $output->writeln('Cleaning avatars...');

        $users = $this->repository->findAll();

        /** @var User $user */
        foreach ($users as $user) {
            $this->updateFacebookAvatar($user, $output);
            $this->removeDeadAvatar($user, $output);
        }

        $this->em->flush();

        $output->writeln('End.');
        $output->writeln((string) $stopwatch->stop('avatar'));
    }

    private function updateFacebookAvatar(User $user, OutputInterface $output): void
    {
        if (is_null($user->getFacebookId())) {
            return;
        }

        try {
            $response = $this->client->request('GET', sprintf(self::API_FB_PICTURE, $user->getFacebookId()));

            if (302 === $response->getStatusCode() && $response->hasHeader('Location')) {
                $user->setProfilePicture($response->getHeader('Location')[0]);
                $this->em->persist($user);
                $output->writeln("Updating $user");
            }
        } catch (TransportExceptionInterface) {
        }
    }

    private function removeDeadAvatar(User $user, OutputInterface $output): void
    {
        if (is_null($user->getProfilePicture())) {
            return;
        }

        try {
            $response = $this->client->request('GET', $user->getProfilePicture());

            if (200 !== $response->getStatusCode()) {
                $user->setProfilePicture(null);
                $this->em->persist($user);

                $output->writeln("Removing avatar for $user");
            }
        } catch (\Exception) {
            return;
        } catch (TransportExceptionInterface) {
        }
    }
}

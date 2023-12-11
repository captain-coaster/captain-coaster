<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AvatarCleanCommand extends Command
{
    final public const API_FB_PICTURE = 'https://graph.facebook.com/v18.0/%d/picture';
    protected static $defaultName = 'avatar:clean';

    public function __construct(private readonly EntityManagerInterface $em, private readonly HttpClientInterface $client, private readonly UserRepository $userRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Update profile pictures');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('avatar');
        $output->writeln('Cleaning avatars...');

        $users = $this->userRepository->findAll();

        $batchSize = 20;
        $i = 0;
        foreach ($users as $user) {
            if ($this->isAvatarDead($user)) {
                $output->writeln("Dead URL for $user");
                $this->updateAvatar($user, $output);
            }

            ++$i;
            if (0 === $i % $batchSize) {
                $output->writeln("Flushing ($i)");
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
        $this->em->clear();

        $output->writeln('End.');
        $output->writeln((string) $stopwatch->stop('avatar'));

        return 0;
    }

    private function updateAvatar(User $user, OutputInterface $output): void
    {
        if (null === $user->getFacebookId()) {
            $user->setProfilePicture(null);
            $this->em->persist($user);

            $output->writeln("Removing profile picture for $user");

            return;
        }

        try {
            $response = $this->client->request('GET', sprintf(self::API_FB_PICTURE, $user->getFacebookId()), ['max_redirects' => 0]);

            if (302 === $response->getStatusCode()) {
                $user->setProfilePicture($response->getHeaders(false)['location'][0]);
                $this->em->persist($user);
                $output->writeln("Updating Facebook picture for: $user");
            }
        } catch (\Exception) {
            // do nothing
        }
    }

    private function isAvatarDead(User $user): bool
    {
        if (null === $user->getProfilePicture()) {
            return false;
        }

        try {
            $response = $this->client->request('GET', $user->getProfilePicture());

            if (200 !== $response->getStatusCode()) {
                return true;
            }
        } catch (\Exception) {
            return true;
        }

        return false;
    }
}

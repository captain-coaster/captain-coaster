<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Aws\S3\S3Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'banner:delete',
    description: 'Delete outdated banner files',
    hidden: false,
)]
class BannerDeleteCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly S3Client $s3Client,
        #[Autowire('%env(string:AWS_S3_CACHE_BUCKET_NAME)%')]
        private readonly string $s3CacheBucket
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = [];

        $stopwatch = new Stopwatch();
        $stopwatch->start('banner');
        $output->writeln('Start deleting banners.');

        $users = $this->userRepository->getUsersWithRecentRatingOrTopUpdate();

        $keys = [];

        /** @var User $user */
        foreach ($users as $user) {
            $output->writeln("User banner: $user (".$user->getId().')...');
            $keys[] = ['Key' => 'banner/'.$user->getId().'.png'];
        }

        if ($keys) {
            $output->writeln('Deleting banners...');
            $response = $this->s3Client->deleteObjects([
                'Bucket' => $this->s3CacheBucket,
                'Delete' => [
                    'Objects' => $keys,
                ],
            ]);

            $output->writeln('Deleted '.\count($response['Deleted']).' banners.');
        }

        $output->writeln('End of command.');
        $output->writeln((string) $stopwatch->stop('banner'));

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Command to migrate user profile pictures from external URLs (Google/Facebook) to S3 storage.
 */
class ProfilePictureMigrationCommand extends Command
{
    protected static $defaultName = 'app:profile-picture-migrate';
    protected static $defaultDescription = 'Migrate existing profile pictures from Google/Facebook URLs to S3 storage';

    // Batch size for processing users to avoid memory issues
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly Filesystem $profilePicturesFilesystem,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $client
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode - no actual changes will be made')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of users to process in each batch', self::BATCH_SIZE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        // Statistics counters

        $totalProcessed = 0;
        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;

        // Get total count for progress bar
        $totalUsers = $this->userRepository->count([]);
        $io->info(\sprintf('Found %d users to process', $totalUsers));

        // Create progress bar
        $progressBar = new ProgressBar($output, $totalUsers);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        // Process users in batches
        $offset = 0;
        while ($offset < $totalUsers) {
            $users = $this->userRepository->findBy([], ['id' => 'ASC'], $batchSize, $offset);

            foreach ($users as $user) {
                $profilePictureUrl = $user->getProfilePicture();
                $progressBar->advance();
                ++$totalProcessed;

                // Skip users without profile picture or with non-external profile pictures
                if (!$profilePictureUrl
                    || (!str_contains($profilePictureUrl, 'googleusercontent.com')
                     && !str_contains($profilePictureUrl, 'graph.facebook.com'))) {
                    ++$skippedCount;
                    continue;
                }

                // Process the profile picture
                $result = $this->processProfilePicture($user, $profilePictureUrl, $dryRun, $io);
                if ($result) {
                    ++$successCount;
                } else {
                    ++$failureCount;
                }
            }

            // Clear entity manager to avoid memory leaks
            $this->em->clear();
            $offset += $batchSize;
        }

        $progressBar->finish();
        $io->newLine(2);

        // Display statistics
        $io->table(
            ['Total Users', 'Processed', 'Successful', 'Failed', 'Skipped'],
            [[$totalUsers, $totalProcessed, $successCount, $failureCount, $skippedCount]]
        );

        $io->success('Profile picture migration complete.');

        return Command::SUCCESS;
    }

    /** Process a single profile picture migration */
    private function processProfilePicture(User $user, string $profilePictureUrl, bool $dryRun, SymfonyStyle $io): bool
    {
        try {
            // Fetch profile picture content using HTTP client
            $response = $this->client->request('GET', $profilePictureUrl);

            if (200 !== $response->getStatusCode()) {
                $this->logger->warning(\sprintf(
                    'Failed to download profile picture for user %s: HTTP status %d',
                    $user->getEmail(),
                    $response->getStatusCode()
                ));

                return false;
            }

            $profilePictureContent = $response->getContent();
            $contentType = $response->getHeaders()['content-type'][0] ?? null;

            if (null === $contentType) {
                $this->logger->warning(\sprintf(
                    'Could not determine content type for user %s',
                    $user->getEmail()
                ));

                return false;
            }

            // Extract file extension from content type
            $extension = $this->getExtensionFromContentType($contentType);
            if (!$extension) {
                $this->logger->warning(\sprintf(
                    'Could not determine file extension for user %s, Content-Type: %s',
                    $user->getEmail(),
                    $contentType
                ));

                return false;
            }

            // Generate unique filename with user ID
            $profilePictureFilename = 'pp_'.$user->getId().'_'.uniqid().'.'.$extension;
            $profilePicturePath = $profilePictureFilename;

            if ($dryRun) {
                $this->logger->info(\sprintf(
                    'Dry run: would upload profile picture for user %s to S3 as %s',
                    $user->getEmail(),
                    $profilePicturePath
                ));

                return true;
            }

            // Save to filesystem and update user
            $this->profilePicturesFilesystem->write($profilePicturePath, $profilePictureContent);
            $user->setProfilePicture($profilePicturePath);
            $this->em->persist($user);
            $this->em->flush();

            $this->logger->info(\sprintf(
                'Successfully migrated profile picture for user %s',
                $user->getEmail()
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Error while migrating profile picture for user %s: %s',
                $user->getEmail(),
                $e->getMessage()
            ));

            return false;
        }
    }

    /** Extract file extension from content type */
    private function getExtensionFromContentType(string $contentType): ?string
    {
        $pos = strrpos($contentType, '/');
        if (false === $pos) {
            return null;
        }

        $extension = substr($contentType, $pos + 1);

        // Handle special cases
        if ('jpeg' === $extension) {
            return 'jpg';
        }

        return $extension;
    }
}

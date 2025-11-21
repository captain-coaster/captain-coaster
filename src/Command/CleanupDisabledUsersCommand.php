<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImageLikeService;
use App\Service\ReviewScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-disabled-users',
    description: 'Remove upvotes and image likes from disabled users',
)]
class CleanupDisabledUsersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReviewScoreService $reviewScoreService,
        private readonly ImageLikeService $imageLikeService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Cleaning up disabled users interactions');

        $conn = $this->entityManager->getConnection();

        try {
            // Count affected records before deletion
            $upvotesCount = $conn->fetchOne('
                SELECT COUNT(*) 
                FROM review_upvote ru
                INNER JOIN users u ON ru.user_id = u.id
                WHERE u.enabled = 0
            ');

            $likesCount = $conn->fetchOne('
                SELECT COUNT(*) 
                FROM liked_image li
                INNER JOIN users u ON li.user_id = u.id
                WHERE u.enabled = 0
            ');

            $io->info(\sprintf('Found %d upvotes and %d image likes from disabled users', $upvotesCount, $likesCount));

            if (0 === $upvotesCount && 0 === $likesCount) {
                $io->success('No cleanup needed - all interactions are from enabled users.');

                return Command::SUCCESS;
            }

            // Remove review upvotes from disabled users
            $io->info('Removing review upvotes from disabled users...');
            $conn->executeStatement('
                DELETE ru FROM review_upvote ru
                INNER JOIN users u ON ru.user_id = u.id
                WHERE u.enabled = 0
            ');

            // Remove image likes from disabled users
            $io->info('Removing image likes from disabled users...');
            $conn->executeStatement('
                DELETE li FROM liked_image li
                INNER JOIN users u ON li.user_id = u.id
                WHERE u.enabled = 0
            ');

            // Recalculate all image like counters
            $io->info('Updating image like counters...');
            $this->imageLikeService->updateAllLikeCounts();

            // Recalculate all review scores (handles counters and weighted scoring)
            $io->info('Recalculating review scores with weighted voting...');
            $this->reviewScoreService->updateAllScores();

            $io->success(\sprintf(
                'Successfully removed %d upvotes and %d image likes from disabled users.',
                $upvotesCount,
                $likesCount
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

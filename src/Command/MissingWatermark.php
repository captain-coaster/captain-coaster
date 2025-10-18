<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'watermark:fix',
    description: 'Fix images from IDs 16749 to 23361 where watermark is missing',
    hidden: false,
)]
class MissingWatermark extends Command
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ImageManager $imageManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode - no actual changes will be made')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of images to process in each batch', self::BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Starting...');

        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        // Statistics counters
        $totalProcessed = 0;
        $successCount = 0;
        $failureCount = 0;
        $offset = 0;

        // Get total images count efficiently
        $totalImages = $this->em->getRepository(Image::class)->countWatermarkToFix();
        $io->writeln($totalImages.' images with watermark to fix');

        // Create progress bar
        $progressBar = new ProgressBar($output, $totalImages);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        // Process images in batches
        while ($offset < $totalImages) {
            $images = $this->em->getRepository(Image::class)->findWatermarkToFix($batchSize, $offset);

            foreach ($images as $image) {
                $progressBar->advance();
                ++$totalProcessed;
                $this->logger->info(\sprintf(
                    'Process image %s (ID: %d)',
                    $image->getFilename(),
                    $image->getId()
                ));

                // Delete cached versions
                if (!$dryRun) {
                    try {
                        $this->imageManager->removeCache($image);
                        ++$successCount;
                    } catch (\Exception $e) {
                        $this->logger->error(\sprintf(
                            'Failed to delete cached versions for image ID %d: %s',
                            $image->getId(),
                            $e->getMessage()
                        ));
                        ++$failureCount;
                        continue;
                    }
                } else {
                    ++$successCount; // Count as success in dry-run mode
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
            ['Total Images', 'Processed', 'Successful', 'Failed'],
            [[$totalImages, $totalProcessed, $successCount, $failureCount]]
        );

        $io->success('Images watermark migration complete.');

        return Command::SUCCESS;
    }
}

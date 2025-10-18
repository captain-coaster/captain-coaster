<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use App\Service\ImageManager;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'watermark:fix',
    description: 'Add metadata to images before ID 23361 and clear cache for IDs 16749-23361',
    hidden: false,
)]
class MissingWatermark extends Command
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ImageManager $imageManager,
        private readonly S3Client $s3Client,
        #[Autowire('%env(string:AWS_S3_BUCKET_NAME)%')]
        private readonly string $s3Bucket,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run mode - no actual changes will be made')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of images to process in each batch', self::BATCH_SIZE)
            ->addOption('test-run', null, InputOption::VALUE_NONE, 'Test run mode - process only the first batch and stop')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force re-processing of files that already have metadata');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln('Starting...');

        $dryRun = $input->getOption('dry-run');
        $testRun = $input->getOption('test-run');
        $force = $input->getOption('force');
        $verbose = $output->isVerbose();
        $batchSize = (int) $input->getOption('batch-size');

        // Statistics counters
        $totalProcessed = 0;
        $metadataAdded = 0;
        $cacheCleared = 0;
        $failureCount = 0;
        $offset = 0;

        // Get total images count efficiently
        $actualTotal = $this->countImagesForMetadata();
        $totalImages = $testRun ? min($batchSize, $actualTotal) : $actualTotal;
        $io->writeln($testRun ? 'TEST RUN: Processing first '.$totalImages.' images only' : $totalImages.' images to process for metadata');

        // Create progress bar
        $progressBar = new ProgressBar($output, $totalImages);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        // Process images in batches
        while ($offset < $actualTotal) {
            $images = $this->findImagesForMetadata($batchSize, $offset);

            // Break if no more images found
            if (empty($images)) {
                break;
            }

            foreach ($images as $image) {
                $progressBar->advance();
                ++$totalProcessed;

                if ($verbose) {
                    $io->writeln(\sprintf(
                        'Processing image %s (ID: %d)',
                        $image->getFilename(),
                        $image->getId()
                    ));
                }

                try {
                    // Check and add metadata if missing
                    $headResult = $this->s3Client->headObject([
                        'Bucket' => $this->s3Bucket,
                        'Key' => $image->getFilename(),
                    ]);

                    if ($force || !isset($headResult['Metadata']['watermark'])) {
                        if (!$dryRun) {
                            // Use copyObject to add metadata (S3 optimizes same-source copies)
                            $this->s3Client->copyObject([
                                'Bucket' => $this->s3Bucket,
                                'Key' => $image->getFilename(),
                                'CopySource' => $this->s3Bucket.'/'.$image->getFilename(),
                                'ContentType' => $headResult['ContentType'] ?? 'image/jpeg',
                                'Metadata' => [
                                    'watermark' => $image->isWatermarked() ? '1' : '0',
                                ],
                                'MetadataDirective' => 'REPLACE',
                            ]);
                        }
                        ++$metadataAdded;
                        if ($verbose) {
                            $io->writeln($force && isset($headResult['Metadata']['watermark']) ? '  → Re-processed metadata (forced)' : '  → Added watermark metadata');
                        }
                    }

                    // Clear cache only for images between 16749 and 23361
                    if ($this->needsCacheClearing($image)) {
                        if (!$dryRun) {
                            $this->imageManager->removeCache($image);
                        }
                        ++$cacheCleared;
                        if ($verbose) {
                            $io->writeln('  → Cleared cache');
                        }
                    }
                } catch (\Exception $e) {
                    // Skip missing files, but log other errors
                    if (str_contains($e->getMessage(), 'NoSuchKey')) {
                        $io->writeln(\sprintf(
                            '<comment>File not found for image ID %d (%s), skipping</comment>',
                            $image->getId(),
                            $image->getFilename()
                        ));
                    } else {
                        $io->writeln(\sprintf(
                            '<error>Failed to process image ID %d (%s): %s</error>',
                            $image->getId(),
                            $image->getFilename(),
                            $e->getMessage()
                        ));
                        ++$failureCount;
                    }
                }
            }

            // Clear entity manager to avoid memory leaks
            $this->em->clear();
            $offset += $batchSize;

            // Stop after first batch in test mode
            if ($testRun) {
                break;
            }
        }

        $progressBar->finish();
        $io->newLine(2);

        // Display statistics
        $io->table(
            ['Total Images', 'Processed', 'Metadata Added', 'Cache Cleared', 'Failed'],
            [[$totalImages, $totalProcessed, $metadataAdded, $cacheCleared, $failureCount]]
        );

        $io->success('Images metadata and cache migration complete.');

        return Command::SUCCESS;
    }

    private function findImagesForMetadata(?int $limit, int $offset = 0): array
    {
        $queryBuilder = $this->em
            ->createQueryBuilder()
            ->select('i')
            ->from(Image::class, 'i')
            ->where('i.watermarked = true')
            ->andWhere('i.id < 23361')
            ->orderBy('i.id', 'ASC');

        if ($offset > 0) {
            $queryBuilder->setFirstResult($offset);
        }

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    private function countImagesForMetadata(): int
    {
        return $this->em
            ->createQueryBuilder()
            ->select('count(1)')
            ->from(Image::class, 'i')
            ->where('i.watermarked = true')
            ->andWhere('i.id < 23361')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function needsCacheClearing(Image $image): bool
    {
        return $image->getId() >= 16749 && $image->getId() <= 23361;
    }
}

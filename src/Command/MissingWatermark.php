<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
        private readonly FilesystemOperator $picturesFilesystem,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $client,
        #[Autowire('%env(string:PICTURES_CDN)%')]
        private readonly string $imagesEndpoint
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

        // Get total images count
        $totalImages = \count($this->em->getRepository(Image::class)->findWatermarkToFix(null, $offset));
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

                // Process the image$
                $oldFile = $this->imagesEndpoint.'/1440x1440/'.$image->getFilename();
                $result = $this->processImage($oldFile, $image, $dryRun);
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
            ['Total Images', 'Processed', 'Successful', 'Failed'],
            [[$totalImages, $totalProcessed, $successCount, $failureCount]]
        );

        $io->success('Images watermark migration complete.');

        return Command::SUCCESS;
    }

    private function processImage(string $oldFile, $image, mixed $dryRun): bool
    {
        try {
            if (!$dryRun) {
                $response = $this->client->request('GET', $oldFile);

                if (200 !== $response->getStatusCode()) {
                    $this->logger->warning(\sprintf(
                        'Failed to download image %s: HTTP status %d',
                        $oldFile,
                        $response->getStatusCode()
                    ));
                }

                $newImage = $response->getContent();
                $contentType = $response->getHeaders()['content-type'][0] ?? null;

                if (null === $contentType) {
                    $this->logger->warning(\sprintf(
                        'Could not determine content type for image %s',
                        $oldFile
                    ));
                }
            }

            $newFilename = $this->generateFilename($image->getCoaster()->getSlug());
            $this->logger->info(\sprintf('Generated new filename %s', $newFilename));
            if (!$dryRun) {
                $this->picturesFilesystem->write(
                    $newFilename,
                    $newImage,
                    ['Metadata' => ['watermark' => 1]]
                );

                $this->deleteOldImage($oldFile);
                $this->updateImageFilename($image, $newFilename);

                $this->logger->info(\sprintf(
                    'Successfully fixed watermark for image %s',
                    $image->getId()
                ));
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error(\sprintf(
                'Error while fixing watermark for image %s: %s',
                $image->getId(),
                $e->getMessage()
            ));

            return false;
        }
    }

    /** Generates a new filename like fury-325-carowinds-64429c62b6b23.jpg. */
    private function generateFilename(string $coasterSlug): string
    {
        return \sprintf('%s-%s.%s', $coasterSlug, uniqid(), 'jpg');
    }

    /** Delete the old image. */
    private function deleteOldImage($image): void
    {
        if (null !== $image) {
            try {
                // Add exists check to avoid unnecessary delete attempts
                if ($this->picturesFilesystem->fileExists($image)) {
                    $this->picturesFilesystem->delete($image);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to delete old image: '.$e->getMessage());
            }
        }
    }

    /** Update the filename without affecting updatedAt */
    private function updateImageFilename(Image $image, string $filename): void
    {
        // Use direct SQL to update only the score field without triggering lifecycle callbacks
        $conn = $this->em->getConnection();
        $sql = 'UPDATE image SET filename = :filename WHERE id = :id';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('filename', $filename);
        $stmt->bindValue('id', $image->getId());
        $stmt->executeStatement();

        // Update the entity in memory to reflect the database change
        $image->setFilename($filename);
    }
}

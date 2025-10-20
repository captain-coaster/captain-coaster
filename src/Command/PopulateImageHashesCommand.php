<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-image-hashes',
    description: 'Populate hash field for existing images'
)]
class PopulateImageHashesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageRepository $imageRepository,
        private readonly FilesystemOperator $picturesFilesystem,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $total = $this->imageRepository->count(['hash' => null]);

        if (0 === $total) {
            $io->success('All images already have hashes.');

            return Command::SUCCESS;
        }

        $io->progressStart($total);
        $processed = 0;
        $batchSize = 50;
        $offset = 0;

        while ($offset < $total) {
            $images = $this->imageRepository->findBy(['hash' => null], null, $batchSize, $offset);

            foreach ($images as $image) {
                try {
                    if ($this->picturesFilesystem->fileExists($image->getFilename())) {
                        $content = $this->picturesFilesystem->read($image->getFilename());
                        $hash = dechex(crc32($content));
                        $image->setHash($hash);
                        ++$processed;
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to process image: '.$image->getFilename(), [
                        'error' => $e->getMessage(),
                    ]);
                }

                $io->progressAdvance();
            }

            $this->em->flush();
            $this->em->clear();
            $offset += $batchSize;
        }

        $io->progressFinish();

        $io->success(\sprintf('Processed %d out of %d images.', $processed, $total));

        return Command::SUCCESS;
    }
}

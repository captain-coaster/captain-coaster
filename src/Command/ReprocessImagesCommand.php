<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Service\ImageModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfills GenAI moderation/focal-point analysis for existing images, and re-runs it on
 * demand for already-analyzed ones (e.g. after a prompt/model change).
 *
 * Default (no targeting option): backfill mode, images that were never analyzed
 * (analyzedAt IS NULL) -- covers the full pre-existing stock. Any targeting option forces
 * re-analysis regardless of analyzedAt.
 */
#[AsCommand(name: 'app:reprocess-images', description: 'Backfill or force GenAI moderation/focal-point analysis for images')]
class ReprocessImagesCommand extends Command
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly CoasterRepository $coasterRepository,
        private readonly ImageModerationService $imageModerationService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('ids', null, InputOption::VALUE_REQUIRED, 'Comma-separated Image IDs to force-reanalyze')
            ->addOption('coaster-ids', null, InputOption::VALUE_REQUIRED, 'Comma-separated Coaster IDs -- force-reanalyze each one\'s main image')
            ->addOption('hero', null, InputOption::VALUE_NONE, 'Force-reanalyze the whole homepage hero candidate pool (upcoming/new/trending coasters\' main images + the top-liked photo candidate), not just today\'s pick, since rotation can surface any of them at any time')
            ->addOption('all-main-images', null, InputOption::VALUE_NONE, 'Force-reanalyze every coaster\'s main image')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max number of images to process', 200)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would happen without writing to the database')
            ->setHelp(
                'Examples:'.\PHP_EOL.
                '  php bin/console app:reprocess-images'.\PHP_EOL.
                '  php bin/console app:reprocess-images --ids=123,456'.\PHP_EOL.
                '  php bin/console app:reprocess-images --coaster-ids=12,34'.\PHP_EOL.
                '  php bin/console app:reprocess-images --hero'.\PHP_EOL.
                '  php bin/console app:reprocess-images --all-main-images --limit=5000'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');

        $images = $this->resolveTargetImages($input, $limit);

        if ([] === $images) {
            $io->success('No images to process.');

            return Command::SUCCESS;
        }

        $io->note(\sprintf('Processing %d image(s)%s.', \count($images), $dryRun ? ' (dry run)' : ''));

        $processed = 0;
        $flagged = 0;
        $failed = 0;

        foreach ($images as $image) {
            try {
                $result = $this->imageModerationService->analyze($image);

                if (null === $result) {
                    $io->warning(\sprintf('Image #%d: analysis failed, skipping.', $image->getId()));
                    ++$failed;
                    continue;
                }

                ++$processed;

                if ($dryRun) {
                    $io->writeln(\sprintf('Image #%d: categories=[%s] focal=(%.2f, %.2f)', $image->getId(), implode(',', $result['categories']), $result['focalX'], $result['focalY']));
                    continue;
                }

                $this->imageModerationService->applyResult($image, $result);
                $this->entityManager->flush();

                if ([] !== $result['categories']) {
                    ++$flagged;
                }
            } catch (\Throwable $e) {
                // Large batch volume (the full historical backfill can be tens of thousands of
                // images) -- one bad image shouldn't abort a multi-hour run, unlike
                // AnalyzeReviewsCommand's fail-fast convention for its much smaller batches.
                $io->warning(\sprintf('Image #%d: %s', $image->getId(), $e->getMessage()));
                ++$failed;
            }
        }

        $io->success(\sprintf('Processed %d image(s), %d flagged, %d failed.', $processed, $flagged, $failed));

        return Command::SUCCESS;
    }

    /** @return array<Image> */
    private function resolveTargetImages(InputInterface $input, int $limit): array
    {
        $ids = $input->getOption('ids');
        $coasterIds = $input->getOption('coaster-ids');
        $hero = (bool) $input->getOption('hero');
        $allMainImages = (bool) $input->getOption('all-main-images');

        if (null !== $ids) {
            $images = array_filter(array_map(
                fn (string $id) => $this->imageRepository->find((int) trim($id)),
                explode(',', $ids)
            ));

            return \array_slice($images, 0, $limit);
        }

        if (null !== $coasterIds) {
            $images = [];
            foreach (explode(',', $coasterIds) as $coasterId) {
                $coaster = $this->coasterRepository->find((int) trim($coasterId));
                if (null !== $coaster?->getMainImage()) {
                    $images[] = $coaster->getMainImage();
                }
            }

            return \array_slice($images, 0, $limit);
        }

        if ($hero) {
            return $this->resolveHeroCandidatePool();
        }

        if ($allMainImages) {
            $images = [];
            foreach ($this->coasterRepository->findAll() as $coaster) {
                if (null !== $coaster->getMainImage()) {
                    $images[] = $coaster->getMainImage();
                }
            }

            return \array_slice($images, 0, $limit);
        }

        return $this->imageRepository->findUnanalyzed($limit);
    }

    /**
     * Replicates HeroService::resolve()'s candidate assembly -- the whole pool, not just
     * whatever it would randomly pick right now, since rotation can surface any of the four.
     *
     * @return array<Image>
     */
    private function resolveHeroCandidatePool(): array
    {
        $images = [];

        foreach ([
            $this->coasterRepository->findUpcomingCoaster(),
            $this->coasterRepository->findRecentlyOpenedCoaster(),
            $this->coasterRepository->findTrendingCoaster(),
        ] as $coaster) {
            if (null !== $coaster?->getMainImage()) {
                $images[] = $coaster->getMainImage();
            }
        }

        try {
            $images[] = $this->imageRepository->findFeaturedImage();
        } catch (NoResultException) {
            // no photo clears the like threshold yet -- skip, same as HeroService
        }

        return $images;
    }
}

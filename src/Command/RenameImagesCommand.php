<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use App\Entity\ImageReport;
use App\Repository\ImageRepository;
use App\Service\HeroService;
use App\Service\ImageManager;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Renames photo originals to `{Image.id}.jpg` (pictures delivery plan, step 3) -- captain-infra's
 * v2 layout (`/i/{id}/...`) looks the original up by id, not by the current slug-uniqid/UUID
 * filename. No GenAI involved, unlike app:reprocess-images.
 *
 * Per image: S3 CopyObject the current key to `{id}.jpg` (MetadataDirective COPY -- keeps the
 * focal-x/focal-y/watermark metadata as-is; StorageClass INTELLIGENT_TIERING, since a default
 * COPY would land in STANDARD and a later lifecycle transition bills separately), then update
 * Image::filename and any ImageReport snapshot rows that still carry the old name, then flush.
 * The DB is only touched after a successful copy (idempotent, re-runnable: an image whose
 * filename already matches `{id}.jpg` is skipped). Every successful rename is appended to a log
 * file as `id<TAB>old<TAB>new`, which --purge-old later replays to delete the old keys (not
 * before the legacy URL scheme -- keyed by the old filename -- is retired, plan step 8).
 *
 * Before renaming an image, its S3 metadata is checked against the DB (the source of truth):
 * `ImageManager::writeFocalPointMetadata()` only logs a failed write (R11 in the plan), so the
 * two can silently drift, and a drifted image answers 409 under the v2 scheme instead of
 * silently falling back to the automatic crop. --dry-run reports every mismatch found without
 * writing anything; a real run skips renaming a mismatched image unless --fix-metadata is also
 * given, in which case it rewrites the S3 metadata from the DB first (existing, tested path)
 * and only then renames it -- renaming a still-drifted image would just carry the drift forward
 * under the new key.
 *
 * IMPORTANT -- live impact while the app is still on the legacy URL scheme (before step 4):
 * `PictureUrlSigner`/the Twig macros sign whatever `Image::filename` currently is. The moment
 * this command flips it to `{id}.jpg`, the *next* page render for that photo signs a brand-new
 * legacy path (`/{W}x{H}/{format}/{id}.jpg?s=`) -- a fresh miss on every cache in front of it
 * (Cloudflare, CloudFront, the resized bucket), even though the pixels haven't changed. Nothing
 * breaks (the legacy Lambda finds the original under its new key and regenerates), but it does
 * force an unplanned regeneration + a brief extra-latency hit for every renamed photo's next
 * view, and abandons up to a year of warm cache under the old path. Safe to run against
 * production at any time in principle (reversible from the log, old keys untouched until step
 * 8), but running the *real* (non-dry-run) batches wide before step 4 ships trades a small,
 * spread-out cost now for one less step to sequence later -- a call for whoever is watching the
 * bill and the error rate, not something to default to.
 */
#[AsCommand(name: 'app:rename-images', description: 'Rename photo originals to {Image.id}.jpg (pictures delivery plan, step 3)')]
class RenameImagesCommand extends Command
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageManager $imageManager,
        private readonly HeroService $heroService,
        private readonly S3Client $s3Client,
        #[Autowire('%env(string:AWS_S3_BUCKET_NAME)%')]
        private readonly string $s3OriginalBucket,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Audit every targeted image (S3 metadata vs DB) and list what would be renamed, without writing anything')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max number of images to examine in this run', 5000)
            ->addOption('after-id', null, InputOption::VALUE_REQUIRED, 'Resume after this Image id (exclusive), ordered by id -- e.g. the "last id" a previous run reported')
            ->addOption('fix-metadata', null, InputOption::VALUE_NONE, 'On a real run, rewrite a mismatched image\'s S3 metadata from the DB (ImageManager::writeFocalPointMetadata) before renaming it, instead of skipping it. Ignored with --dry-run, which never writes')
            ->addOption('purge-old', null, InputOption::VALUE_NONE, 'Delete the old S3 key for every rename recorded in the log. Only after the legacy URL scheme is retired (plan step 8) -- it is still keyed by those old filenames until then. Combine with --dry-run to preview')
            ->setHelp(
                'Examples:'.\PHP_EOL.
                '  php bin/console app:rename-images --dry-run'.\PHP_EOL.
                '  php bin/console app:rename-images --limit=2000'.\PHP_EOL.
                '  php bin/console app:rename-images --after-id=2000 --limit=2000'.\PHP_EOL.
                '  php bin/console app:rename-images --fix-metadata'.\PHP_EOL.
                '  php bin/console app:rename-images --purge-old --dry-run'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('purge-old')) {
            return $this->purgeOld($io, (bool) $input->getOption('dry-run'));
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');
        $afterId = $input->getOption('after-id');
        $afterId = null !== $afterId ? (int) $afterId : null;
        $fixMetadata = (bool) $input->getOption('fix-metadata') && !$dryRun;

        if ($input->getOption('fix-metadata') && $dryRun) {
            $io->note('--fix-metadata is ignored with --dry-run: a dry run never writes, mismatches are only reported.');
        }

        $images = $this->imageRepository->findPhotosOrderedById($afterId, $limit);

        if ([] === $images) {
            $io->success('No images to examine.');

            return Command::SUCCESS;
        }

        return $dryRun
            ? $this->auditOnly($io, $images)
            : $this->rename($io, $images, $fixMetadata);
    }

    /** @param array<Image> $images */
    private function auditOnly(SymfonyStyle $io, array $images): int
    {
        $examined = 0;
        $wouldRename = 0;
        $mismatches = 0;
        $missing = 0;
        $lastId = null;

        foreach ($images as $image) {
            ++$examined;
            $lastId = $image->getId();

            if (!$this->alreadyRenamed($image)) {
                ++$wouldRename;
            }

            try {
                $diffs = $this->auditMismatches($image);
            } catch (\Throwable $e) {
                ++$missing;
                $io->warning(\sprintf('Image #%d: could not read the original (%s)', $image->getId(), $e->getMessage()));
                continue;
            }

            if ([] !== $diffs) {
                ++$mismatches;
                foreach ($diffs as $field => [$db, $s3]) {
                    $io->writeln(\sprintf('Image #%d: %s mismatch -- DB "%s" vs S3 "%s"', $image->getId(), $field, $db, $s3));
                }
            }
        }

        $io->success(\sprintf(
            'Examined %d image(s) (last id %s): %d would be renamed, %d metadata mismatch(es), %d unreadable original(s). No write performed.',
            $examined,
            $lastId ?? 'n/a',
            $wouldRename,
            $mismatches,
            $missing,
        ));

        return $mismatches > 0 || $missing > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /** @param array<Image> $images */
    private function rename(SymfonyStyle $io, array $images, bool $fixMetadata): int
    {
        $renamed = 0;
        $skippedAlready = 0;
        $skippedMismatch = 0;
        $failed = 0;
        $lastId = null;

        foreach ($images as $image) {
            $lastId = $image->getId();

            if ($this->alreadyRenamed($image)) {
                ++$skippedAlready;
                continue;
            }

            try {
                $diffs = $this->auditMismatches($image);
            } catch (\Throwable $e) {
                ++$failed;
                $io->warning(\sprintf('Image #%d: could not read the original (%s), skipping', $image->getId(), $e->getMessage()));
                continue;
            }

            if ([] !== $diffs) {
                if (!$fixMetadata) {
                    ++$skippedMismatch;
                    $io->warning(\sprintf('Image #%d: S3/DB metadata mismatch, skipped (re-run with --fix-metadata, or --dry-run to inspect)', $image->getId()));
                    continue;
                }

                $this->imageManager->writeFocalPointMetadata($image);
            }

            $oldFilename = $image->getFilename();
            $newFilename = $image->getId().'.jpg';

            try {
                $this->s3Client->copyObject([
                    'Bucket' => $this->s3OriginalBucket,
                    'Key' => $newFilename,
                    'CopySource' => rawurlencode("{$this->s3OriginalBucket}/{$oldFilename}"),
                    'MetadataDirective' => 'COPY',
                    'StorageClass' => 'INTELLIGENT_TIERING',
                ]);
            } catch (\Throwable $e) {
                ++$failed;
                $io->warning(\sprintf('Image #%d: copy failed (%s), skipped', $image->getId(), $e->getMessage()));
                continue;
            }

            // DB only touched after a successful copy -- idempotent, re-runnable.
            $image->setFilename($newFilename);
            $this->entityManager->createQuery(
                'UPDATE '.ImageReport::class.' r SET r.imageFilename = :new WHERE r.imageFilename = :old'
            )->setParameter('new', $newFilename)->setParameter('old', $oldFilename)->execute();
            $this->entityManager->flush();

            $this->appendToLog($image->getId(), $oldFilename, $newFilename);
            ++$renamed;
        }

        if ($renamed > 0) {
            // imageFilename snapshots in the cached hero pick can otherwise point at a name
            // that no longer exists on its own (the old key is kept until step 8, so nothing
            // breaks, but the cached pick would keep serving a stale filename until it expires).
            $this->heroService->invalidate();
        }

        $io->success(\sprintf(
            'Renamed %d image(s) (last id examined: %s), %d already done, %d skipped (metadata mismatch), %d failed.',
            $renamed,
            $lastId ?? 'n/a',
            $skippedAlready,
            $skippedMismatch,
            $failed,
        ));

        return $failed > 0 || $skippedMismatch > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function alreadyRenamed(Image $image): bool
    {
        return $image->getFilename() === $image->getId().'.jpg';
    }

    /**
     * HeadObject on the image's *current* key (old or already-renamed, either way the live
     * object) and compares its focal-x/focal-y/watermark metadata against the DB, using the
     * same canonical strings the captain-infra v2 Lambda hashes (plan section 4.3): both sides
     * must converge on the same "no focal point" ('-') / "not watermarked" ('0') fallback for
     * a missing value, or every image without a focal point would show up as a false mismatch.
     *
     * @return array<string, array{0: string, 1: string}> field => [dbCanonical, s3Canonical], empty if none
     */
    private function auditMismatches(Image $image): array
    {
        $result = $this->s3Client->headObject([
            'Bucket' => $this->s3OriginalBucket,
            'Key' => $image->getFilename(),
        ]);
        $metadata = $result['Metadata'] ?? [];

        $diffs = [];

        $dbFocalX = self::canonicalFocal($image->getFocalX());
        $s3FocalX = self::canonicalFocal($metadata['focal-x'] ?? null);
        if ($dbFocalX !== $s3FocalX) {
            $diffs['focal-x'] = [$dbFocalX, $s3FocalX];
        }

        $dbFocalY = self::canonicalFocal($image->getFocalY());
        $s3FocalY = self::canonicalFocal($metadata['focal-y'] ?? null);
        if ($dbFocalY !== $s3FocalY) {
            $diffs['focal-y'] = [$dbFocalY, $s3FocalY];
        }

        $dbWatermark = $image->isWatermarked() ? '1' : '0';
        $s3Watermark = '1' === ($metadata['watermark'] ?? null) ? '1' : '0';
        if ($dbWatermark !== $s3Watermark) {
            $diffs['watermark'] = [$dbWatermark, $s3Watermark];
        }

        return $diffs;
    }

    /** Mirrors captain-infra's canonicalFocalComponent (handler.mjs/v2.mjs): missing/empty -> '-'. */
    private static function canonicalFocal(float|string|null $value): string
    {
        if (null === $value || '' === $value) {
            return '-';
        }

        return \is_float($value) ? (string) $value : $value;
    }

    private function logFilePath(): string
    {
        return $this->projectDir.'/var/rename-images.log';
    }

    private function appendToLog(int $id, string $old, string $new): void
    {
        file_put_contents($this->logFilePath(), "{$id}\t{$old}\t{$new}\n", \FILE_APPEND);
    }

    private function purgeOld(SymfonyStyle $io, bool $dryRun): int
    {
        $path = $this->logFilePath();
        if (!is_file($path)) {
            $io->success('No rename log found -- nothing to purge.');

            return Command::SUCCESS;
        }

        $lines = file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [];
        $toDelete = [];

        foreach ($lines as $line) {
            $parts = explode("\t", $line);
            if (3 !== \count($parts)) {
                continue;
            }
            [, $old, $new] = $parts;
            if ($old !== $new) {
                $toDelete[$old] = true; // dedupe: a re-run of app:rename-images can log the same pair twice
            }
        }

        $keys = array_keys($toDelete);

        if ([] === $keys) {
            $io->success('Rename log has no old keys to purge.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note(\sprintf('Would delete %d old key(s) from s3://%s -- no write performed:', \count($keys), $this->s3OriginalBucket));
            foreach ($keys as $key) {
                $io->writeln($key);
            }

            return Command::SUCCESS;
        }

        // S3 DeleteObjects takes at most 1000 keys per call.
        $deleted = 0;
        foreach (array_chunk($keys, 1000) as $chunk) {
            $this->s3Client->deleteObjects([
                'Bucket' => $this->s3OriginalBucket,
                'Delete' => ['Objects' => array_map(static fn (string $key) => ['Key' => $key], $chunk)],
            ]);
            $deleted += \count($chunk);
        }

        $io->success(\sprintf('Deleted %d old key(s) from s3://%s.', $deleted, $this->s3OriginalBucket));

        return Command::SUCCESS;
    }
}

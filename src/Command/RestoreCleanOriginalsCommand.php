<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImageRepository;
use App\Service\ImageManager;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Recovers true, uncompressed originals from local backup disks. Some pre-2023 S3 "originals"
 * are actually already-compressed re-derivatives -- not just the watermark-baked-in subset --
 * so this walks every file on the given backup disk(s), matches it to a DB row by its UUID
 * filename, and overwrites the S3 original only when the backup file is strictly larger than
 * what's currently stored (a cheap HEAD per candidate, not worth skipping).
 *
 * Deliberately does NOT touch `image.watermarked` (DB) or the S3 object's `watermark` metadata
 * key -- both reflect a choice made at upload time, not a technical fact this script is
 * qualified to overwrite. Whatever value is already on the object is carried through unchanged
 * on rewrite. Any decision about images that turn out to be unrecoverable (still watermark-
 * baked-in, no backup ever found) is a separate, later step -- see watermark_no_backup.csv.
 *
 * CloudFront is deliberately NOT invalidated here -- do one broad invalidation by size/format
 * prefix at the very end, once, after every backup disk has been run (see the command's help).
 *
 * Safe to run repeatedly / across multiple disks: the S3 HEAD size comparison is the
 * idempotency check itself -- once an original has been swapped for the backup's copy, a later
 * run (this or another disk) only overwrites it again if it finds something bigger still.
 *
 * restored.csv/skipped.csv accumulate across runs that share the same --report-dir (e.g. several
 * --limit batches against one disk), so re-pointing --report-dir at the same place keeps one
 * running log instead of each run erasing the last one's.
 */
#[AsCommand(
    name: 'app:pictures:restore-clean-originals',
    description: 'Restore true (uncompressed) originals from a local backup disk when larger than what is on S3',
    help: <<<'HELP'
        Dry run first, then --execute once the manifest looks right. Repeat across every backup
        disk you have (each its own --backup-dir), then invalidate CloudFront ONCE at the end --
        by size/format prefix is far cheaper than per-image and stays inside the free tier:

          aws cloudfront create-invalidation --distribution-id <id> --paths \
            "/96x72/*" "/96x96/*" "/192x144/*" "/192x192/*" "/200x150/*" "/280x210/*" \
            "/400x300/*" "/560x420/*" "/600x336/*" "/1200x672/*" "/1440x1440/*"

        (that list is whatever `aws s3api list-objects-v2 --bucket <resized bucket> --delimiter /`
        currently reports as top-level prefixes -- sizes aren't hardcoded anywhere else either,
        re-check it if a template has added a new size since.)
        HELP,
)]
class RestoreCleanOriginalsCommand extends Command
{
    // Same bucket as the live original -- a same-region CopyObject, not a re-upload, so it's
    // near-free -- kept so a bad restore (corrupt/wrong backup file) can be undone by hand.
    private const string ARCHIVE_PREFIX = 'restored-originals-backup/';

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly FilesystemOperator $picturesFilesystem,
        private readonly ImageManager $imageManager,
        private readonly S3Client $s3Client,
        #[Autowire('%env(string:AWS_S3_BUCKET_NAME)%')]
        private readonly string $originalsBucket,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('backup-dir', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Local directory to scan for backups (repeatable, one per disk)')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Actually write to S3. Without it, this is a dry run.')
            ->addOption('report-dir', null, InputOption::VALUE_REQUIRED, 'Directory to write the CSV manifests into', sys_get_temp_dir())
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Only process the first N backup files found (for a test run)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $backupDirs = $input->getOption('backup-dir');
        if ([] === $backupDirs) {
            $io->error('Pass at least one --backup-dir /path/to/disk/backup.');

            return Command::FAILURE;
        }

        $execute = (bool) $input->getOption('execute');
        $limit = null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null;
        $reportDir = rtrim((string) $input->getOption('report-dir'), '/');

        // Created upfront -- a report-dir typo shouldn't be discovered only after the loop
        // has already written to S3.
        if (!is_dir($reportDir) && !mkdir($reportDir, 0o755, true) && !is_dir($reportDir)) {
            $io->error(\sprintf('Could not create --report-dir "%s".', $reportDir));

            return Command::FAILURE;
        }

        $io->note($execute
            ? 'LIVE RUN -- will overwrite S3 originals with larger backup files.'
            : 'DRY RUN -- no writes will happen. Pass --execute once the manifest looks right.');

        $io->section('Indexing local backup disk(s)');
        $backupIndex = $this->indexBackups($backupDirs);
        if (null !== $limit) {
            $backupIndex = \array_slice($backupIndex, 0, $limit, true);
        }
        $io->text(\sprintf('%d backup file(s) to check.', \count($backupIndex)));

        $restored = [];
        $skipped = [];

        $io->progressStart(\count($backupIndex));
        foreach ($backupIndex as $uuid => $localPath) {
            $image = $this->imageRepository->findOneByUuid($uuid);
            if (null === $image) {
                $skipped[] = [$uuid, $localPath, null, 'no_db_match'];
                $io->progressAdvance();
                continue;
            }

            $localSize = filesize($localPath);
            if (false === $localSize) {
                $skipped[] = [$uuid, $localPath, $image->getId(), 'local_file_unreadable'];
                $io->progressAdvance();
                continue;
            }

            $filename = $image->getFilename();

            try {
                $head = $this->s3Client->headObject(['Bucket' => $this->originalsBucket, 'Key' => $filename]);
            } catch (S3Exception $e) {
                $skipped[] = [$uuid, $localPath, $image->getId(), 's3_head_failed: '.$e->getAwsErrorCode()];
                $io->progressAdvance();
                continue;
            }

            $s3Size = (int) $head->get('ContentLength');
            if ($localSize <= $s3Size) {
                $skipped[] = [$uuid, $localPath, $image->getId(), \sprintf('s3_already_larger_or_equal (local=%d, s3=%d)', $localSize, $s3Size)];
                $io->progressAdvance();
                continue;
            }

            $content = file_get_contents($localPath);
            if (false === $content || false === @getimagesizefromstring($content)) {
                $skipped[] = [$uuid, $localPath, $image->getId(), 'unreadable_or_not_an_image'];
                $io->progressAdvance();
                continue;
            }

            if ($execute) {
                $this->s3Client->copyObject([
                    'Bucket' => $this->originalsBucket,
                    'Key' => self::ARCHIVE_PREFIX.$filename,
                    'CopySource' => rawurlencode($this->originalsBucket.'/'.$filename),
                ]);

                // Carry the existing `watermark` metadata through unchanged -- it's not this
                // script's call to make, and a plain PutObject would otherwise silently drop it.
                $existingMetadata = $head->get('Metadata') ?? [];
                $writeOptions = [];
                if (isset($existingMetadata['watermark'])) {
                    $writeOptions['Metadata'] = ['watermark' => $existingMetadata['watermark']];
                }

                $this->picturesFilesystem->write($filename, $content, $writeOptions);
                $this->imageManager->removeCache($image);
            }

            $restored[] = [$uuid, $localPath, $image->getId(), $filename, $image->getCreatedAt()->format('Y-m-d'), $localSize, $s3Size, $execute ? 'restored' : 'would_restore'];
            $io->progressAdvance();
        }
        $io->progressFinish();

        // Appended, not overwritten -- running several batches (e.g. --limit 100 at a time)
        // against the same --report-dir builds one running log across all of them instead of
        // each batch erasing the last one's record of what it touched.
        $this->writeCsv($reportDir.'/restored.csv', ['uuid', 'local_path', 'id', 'filename', 'created_at', 'local_size', 's3_size', 'status'], $restored, append: true);
        $this->writeCsv($reportDir.'/skipped.csv', ['uuid', 'local_path', 'id', 'reason'], $skipped, append: true);

        // created_at is column index 4 -- see the restored.csv header just above.
        $restoredDates = array_column($restored, 4);
        sort($restoredDates);
        $dateRange = [] !== $restoredDates ? \sprintf('%s to %s', reset($restoredDates), end($restoredDates)) : 'n/a';

        // Informational cross-reference for the separate, later decision: of the images known
        // to still have the watermark baked in, which ones has NO backup disk covered so far?
        $stillUnrecoverable = [];
        foreach ($this->imageRepository->findWatermarkBakedIn() as $image) {
            $uuid = pathinfo($image->getFilename(), \PATHINFO_FILENAME);
            if (!isset($backupIndex[$uuid])) {
                $stillUnrecoverable[] = [$image->getId(), $image->getFilename(), $image->getCreatedAt()?->format('Y-m-d')];
            }
        }
        // Overwritten, not appended -- unlike restored/skipped.csv above, this is a fresh
        // full recomputation every run, not a per-file log entry -- appending would just pile
        // up near-duplicate full scans on every invocation.
        $this->writeCsv($reportDir.'/watermark_no_backup.csv', ['id', 'filename', 'created_at'], $stillUnrecoverable);

        $io->success(\sprintf(
            '%d restored%s (created %s), %d skipped -- manifests in %s/. %d watermark-baked-in image(s) still have no backup anywhere checked so far (watermark_no_backup.csv).',
            \count($restored),
            $execute ? '' : ' (would be)',
            $dateRange,
            \count($skipped),
            $reportDir,
            \count($stillUnrecoverable)
        ));

        return Command::SUCCESS;
    }

    /**
     * @param string[] $dirs
     *
     * @return array<string, string> UUID => absolute local path
     */
    private function indexBackups(array $dirs): array
    {
        $index = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || str_starts_with($file->getFilename(), '._')) {
                    continue;
                }
                $uuid = pathinfo($file->getFilename(), \PATHINFO_FILENAME);
                // First disk to claim a UUID wins -- pass --backup-dir in trust order.
                $index[$uuid] ??= $file->getPathname();
            }
        }

        return $index;
    }

    /**
     * @param string[]                                $header
     * @param array<int, array<int, string|int|null>> $rows
     */
    private function writeCsv(string $path, array $header, array $rows, bool $append = false): void
    {
        $writeHeader = !$append || !is_file($path);
        $handle = fopen($path, $append ? 'a' : 'w');
        if (false === $handle) {
            return;
        }
        if ($writeHeader) {
            fputcsv($handle, $header);
        }
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}

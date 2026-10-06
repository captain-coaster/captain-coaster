<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\HeroService;
use App\Service\ImageManager;
use Aws\S3\Exception\S3Exception;
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
 * Puts back the orientation of the originals restored by app:pictures:restore-clean-originals.
 * The backup-disk files hold the sensor pixels with their EXIF stripped, so a portrait or
 * upside-down shot is stored turned and nothing tells the resizer; the file they replaced (kept
 * under `restored-originals-backup/`) was upright.
 *
 * Fix: an EXIF orientation tag is added to the original (a 36-byte APP1 segment, no pixel is
 * re-encoded). captain-infra's resizer already auto-orients on it and reads the focal point
 * relative to the displayed image.
 *
 * Input is a CSV `id,old_key,orientation` (no header): the image id, the name the original had
 * when it was restored (its backup is `restored-originals-backup/{old_key}`), and the EXIF
 * orientation that makes it upright (3 = 180, 6 = 90 clockwise, 8 = 90 counter-clockwise). The
 * list comes from an audit outside the app (stored pixels compared with the backup).
 *
 * The v2 URL hashes the focal point and `rev`, not the file: `rev` is bumped, so every fixed
 * photo gets a new URL and no browser or CDN keeps the turned variant. The focal point:
 *  - detected on the turned image (analysed after the restore): moved to where the same spot
 *    lands once the image is upright;
 *  - detected on the upright image (analysed before the restore, or after it but on a legacy
 *    1440 variant generated before it): already right, kept;
 *  - none: stays none.
 *
 * Per image, on --execute: PutObject (same key, metadata from ImageManager::originalMetadata(),
 * Intelligent-Tiering), then `hash`, `rev` and the focal in plain SQL (no flush: Image::updatedAt
 * must not move), then the stale variants are deleted. Re-runnable: an original that already has
 * an EXIF segment is skipped.
 */
#[AsCommand(name: 'app:pictures:fix-orientation', description: 'Add the missing EXIF orientation to restored originals (dry run unless --execute)')]
class FixOrientationCommand extends Command
{
    private const string ARCHIVE_PREFIX = 'restored-originals-backup/';

    /** PICTURES_V2 switch: an analysis after it read the v2 1440 variant, so the turned original. */
    private const string V2_SWITCH = '2026-10-02T06:57:00+00:00';

    /** The legacy 1440 jpg variant ImageModerationService::analyze() fetched before the switch. */
    private const string LEGACY_ANALYSIS_VARIANT = '1440x1440/jpg/';

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageManager $imageManager,
        private readonly HeroService $heroService,
        private readonly S3Client $s3Client,
        #[Autowire('%env(string:AWS_S3_BUCKET_NAME)%')]
        private readonly string $originalsBucket,
        #[Autowire('%env(string:AWS_S3_CACHE_BUCKET_NAME)%')]
        private readonly string $cacheBucket,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'CSV of `id,old_key,orientation` rows')
            ->addOption('id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these ids of the file (repeatable)')
            ->addOption('execute', null, InputOption::VALUE_NONE, 'Write to S3 and the database (default is a dry run)')
            ->setHelp(
                'Examples:'.\PHP_EOL.
                '  php bin/console app:pictures:fix-orientation --file=orientation.csv --id=9427'.\PHP_EOL.
                '  php bin/console app:pictures:fix-orientation --file=orientation.csv --id=9427 --execute'.\PHP_EOL.
                '  php bin/console app:pictures:fix-orientation --file=orientation.csv --execute'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $execute = (bool) $input->getOption('execute');
        $file = $input->getOption('file');
        if (!\is_string($file) || !is_file($file)) {
            $io->error('Pass --file=/path/to/orientation.csv.');

            return Command::INVALID;
        }

        $only = array_map(intval(...), (array) $input->getOption('id'));
        $rows = [];
        $handle = fopen($file, 'r');
        while (false !== $handle && false !== ($line = fgetcsv($handle, escape: ''))) {
            if (3 === \count($line) && ctype_digit((string) $line[0]) && ([] === $only || \in_array((int) $line[0], $only, true))) {
                $rows[] = [(int) $line[0], (string) $line[1], (int) $line[2]];
            }
        }

        $report = [];
        $fixed = 0;
        $failed = 0;
        foreach ($rows as [$id, $oldKey, $orientation]) {
            try {
                $result = $this->fix($id, $oldKey, $orientation, $execute);
            } catch (\Throwable $e) {
                $result = ['failed: '.$e->getMessage(), '', ''];
            }
            $fixed += (int) str_starts_with($result[0], 'fixed') + (int) str_starts_with($result[0], 'would fix');
            $failed += (int) str_starts_with($result[0], 'failed');
            $report[] = [$id, $orientation, ...$result];
        }

        if ($execute && $fixed > 0) {
            // The cached hero pick holds a PictureRef with the previous focal point and rev.
            $this->heroService->invalidate();
        }

        $io->table(['id', 'orientation', 'result', 'focal', 'url'], $report);
        $io->success(\sprintf('%d image(s) %s, %d skipped, %d failed.', $fixed, $execute ? 'fixed' : 'would be fixed', \count($rows) - $fixed - $failed, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return array{string, string, string} result, focal change, what happens to the URL */
    private function fix(int $id, string $oldKey, int $orientation, bool $execute): array
    {
        if (!\in_array($orientation, [3, 6, 8], true)) {
            return ['skipped: orientation must be 3, 6 or 8', '', ''];
        }
        $image = $this->imageRepository->find($id);
        if (!$image instanceof Image) {
            return ['skipped: no such image', '', ''];
        }

        $restoredAt = $this->lastModified($this->originalsBucket, self::ARCHIVE_PREFIX.$oldKey);
        if (null === $restoredAt) {
            return ['skipped: no backup, not a restored original', '', ''];
        }

        $original = $this->s3Client->getObject(['Bucket' => $this->originalsBucket, 'Key' => $image->getFilename()]);
        $bytes = (string) $original['Body'];
        if (self::hasExif($bytes)) {
            return ['skipped: already has EXIF', '', ''];
        }
        $size = @getimagesizefromstring($bytes);
        $backupSize = @getimagesizefromstring((string) $this->s3Client->getObject(['Bucket' => $this->originalsBucket, 'Key' => self::ARCHIVE_PREFIX.$oldKey])['Body']);
        if (false === $size || false === $backupSize) {
            return ['skipped: unreadable original or backup', '', ''];
        }
        // A quarter turn swaps portrait and landscape against the upright backup, a half turn does not.
        $turned = ($size[0] > $size[1]) !== ($backupSize[0] > $backupSize[1]);
        if ($size[0] !== $size[1] && $backupSize[0] !== $backupSize[1] && $turned !== (3 !== $orientation)) {
            return ['skipped: orientation contradicts the backup dimensions', '', ''];
        }

        $focal = [$image->getFocalX(), $image->getFocalY()];
        $newFocal = $focal;
        $focalNote = 'none';
        if (null !== $focal[0] && null !== $focal[1]) {
            $focalNote = 'kept';
            if ($this->focalIsOnTurnedImage($image, $oldKey, $restoredAt)) {
                $newFocal = self::turnFocal($focal[0], $focal[1], $orientation);
                $focalNote = \sprintf('turned %s,%s -> %s,%s', $focal[0], $focal[1], $newFocal[0], $newFocal[1]);
            }
        }
        $rev = ($image->getRev() ?? 0) + 1;
        $urlNote = 'new URL: rev '.$rev;

        if (!$execute) {
            return ['would fix', $focalNote, $urlNote];
        }

        // The entity only carries the new values to originalMetadata(); it is never flushed.
        $image->setFocalX($newFocal[0]);
        $image->setFocalY($newFocal[1]);
        $image->setRev($rev);

        $fixedBytes = self::withOrientation($bytes, $orientation);
        $this->s3Client->putObject([
            'Bucket' => $this->originalsBucket,
            'Key' => $image->getFilename(),
            'Body' => $fixedBytes,
            'Metadata' => ImageManager::originalMetadata($image),
            'ContentType' => $original['ContentType'] ?? Image::MIME_TYPE,
            'StorageClass' => 'INTELLIGENT_TIERING',
        ]);

        // S3 first: the Lambda checks the URL against the metadata, the app signs from the DB.
        $row = ['hash' => hash('sha256', $fixedBytes), 'rev' => $rev];
        if ($newFocal !== $focal) {
            $row += ['focal_x' => $newFocal[0], 'focal_y' => $newFocal[1]];
        }
        $this->entityManager->getConnection()->update('image', $row, ['id' => $id]);

        $this->imageManager->removeVariants($image);

        return ['fixed', $focalNote, $urlNote];
    }

    /**
     * Whether the stored focal point was detected on the turned pixels. The analysis reads the
     * 1440 jpg variant: before the restore it was upright; between the restore and the v2 switch
     * it was the legacy variant, upright only if that object predates the restore.
     */
    private function focalIsOnTurnedImage(Image $image, string $oldKey, \DateTimeInterface $restoredAt): bool
    {
        $analyzedAt = $image->getAnalyzedAt();
        if (null === $analyzedAt || $analyzedAt < $restoredAt) {
            return false;
        }
        if ($analyzedAt >= new \DateTimeImmutable(self::V2_SWITCH)) {
            return true;
        }
        $variantAt = $this->lastModified($this->cacheBucket, self::LEGACY_ANALYSIS_VARIANT.$oldKey);

        return null === $variantAt || $variantAt >= $restoredAt;
    }

    private function lastModified(string $bucket, string $key): ?\DateTimeInterface
    {
        try {
            $modified = $this->s3Client->headObject(['Bucket' => $bucket, 'Key' => $key])['LastModified'] ?? null;
        } catch (S3Exception $e) {
            // The app user can list both buckets, so a missing key answers 404; a 403 is a
            // credentials problem and must not read as "no backup".
            if (404 === $e->getStatusCode()) {
                return null;
            }
            throw $e;
        }

        return $modified instanceof \DateTimeInterface ? $modified : null;
    }

    /**
     * Where a focal point of the stored (turned) image lands once `orientation` is applied.
     *
     * @return array{float, float}
     */
    public static function turnFocal(float $x, float $y, int $orientation): array
    {
        [$x, $y] = match ($orientation) {
            3 => [1 - $x, 1 - $y],
            6 => [1 - $y, $x],
            8 => [$y, 1 - $x],
            default => [$x, $y],
        };

        return [round($x, 4), round($y, 4)];
    }

    public static function hasExif(string $jpeg): bool
    {
        return str_contains(substr($jpeg, 0, 65536), "Exif\0\0");
    }

    /** Insert a minimal EXIF APP1 segment (one IFD entry: Orientation) after SOI and the JFIF APP0, if any. */
    public static function withOrientation(string $jpeg, int $orientation): string
    {
        if (!str_starts_with($jpeg, "\xFF\xD8")) {
            throw new \InvalidArgumentException('Not a JPEG.');
        }
        $offset = 2;
        if ("\xFF\xE0" === substr($jpeg, 2, 2)) {
            // APP0 length, big-endian, counts its own two bytes but not the marker.
            $offset += 2 + (\ord($jpeg[4]) << 8 | \ord($jpeg[5]));
        }

        $tiff = "MM\0*".pack('N', 8)        // big-endian TIFF header, IFD0 at offset 8
            .pack('n', 1)                   // one entry
            .pack('nnNnn', 0x0112, 3, 1, $orientation, 0) // Orientation, SHORT, count 1
            .pack('N', 0);                  // no next IFD
        $payload = "Exif\0\0".$tiff;

        return substr($jpeg, 0, $offset)."\xFF\xE1".pack('n', \strlen($payload) + 2).$payload.substr($jpeg, $offset);
    }
}

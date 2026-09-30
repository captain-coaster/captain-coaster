<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Message\AnalyzeImageMessage;
use App\Repository\ImageRepository;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

class ImageManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly FilesystemOperator $picturesFilesystem,
        private readonly S3Client $s3Client,
        private readonly ImageRepository $imageRepository,
        #[Autowire('%env(string:AWS_S3_CACHE_BUCKET_NAME)%')]
        private readonly string $s3CacheBucket,
        #[Autowire('%env(string:AWS_S3_BUCKET_NAME)%')]
        private readonly string $s3OriginalBucket,
        private readonly FilesystemOperator $picturesVariantsFilesystem,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Store a new upload. The original is named after the id, so the row comes first; a failed
     * S3 write rolls it back. The analysis is dispatched once both exist, never before the commit.
     */
    public function store(Image $image): void
    {
        $this->setImageHash($image);
        $image->setFilename(''); // NOT NULL, set once the id exists

        $this->em->wrapInTransaction(function () use ($image): void {
            $this->em->persist($image);
            $this->em->flush();
            $image->setFilename($this->upload($image));
            $this->em->flush();
        });

        $this->messageBus->dispatch(new AnalyzeImageMessage($image->getId()));
    }

    /**
     * Write the uploaded original as `{id}.jpg`, the key the v2 image Lambda reads (uploads are
     * JPEG only, see Image::$file), so the image needs its id first.
     */
    public function upload(Image $image): string
    {
        $filename = $image->getId().'.jpg';

        $this->picturesFilesystem->write(
            $filename,
            $image->getFile()->getContent(),
            ['Metadata' => ['watermark' => $image->isWatermarked() ? '1' : '0'], 'ContentType' => Image::MIME_TYPE]
        );

        return $filename;
    }

    /**
     * An image with the exact same bytes, or null. Images stored before the SHA-256 switch still
     * hold an unpadded CRC32 hash: matched too until they are backfilled.
     */
    public function isDuplicate(UploadedFile $file): ?Image
    {
        $content = file_get_contents($file->getPathname());
        if (false === $content) {
            return null;
        }

        return $this->imageRepository->findOneBy(['hash' => [hash('sha256', $content), dechex(crc32($content))]]);
    }

    /** SHA-256 of the uploaded bytes, the key isDuplicate() looks up. */
    public function setImageHash(Image $image): void
    {
        if ($image->getFile()) {
            $content = file_get_contents($image->getFile()->getPathname());
            if (false !== $content) {
                $image->setHash(hash('sha256', $content));
            }
        }
    }

    /** Remove file from abstracted filesystem (currently S3). */
    public function remove(string $filename): void
    {
        $this->picturesFilesystem->delete($filename);
    }

    /**
     * Delete every v2 variant of a photo (`i/{id}/`, all versions, sizes and formats). Cloudflare
     * may still serve a cached copy until its TTL; purging it is a separate, later step (D16).
     */
    public function removeVariants(Image $image): void
    {
        try {
            $this->picturesVariantsFilesystem->deleteDirectory('i/'.$image->getId());
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete photo variants', ['id' => $image->getId(), 'error' => $e->getMessage()]);
        }
    }

    /**
     * The resizer can encode to any of these -- stable, unlike the sizes below, which are
     * whatever width x height each template happens to request. Not worth a config lookup.
     */
    private const CACHED_FORMATS = ['jpg', 'avif'];

    /**
     * Remove every resized/re-encoded variant of an image from the S3 Cache Bucket.
     *
     * The destination key is {size}/{format}/{name}.{format} -- and the CloudFront origin
     * group requires that key to match the signed request path exactly (cache miss on the S3
     * origin fails over to the Lambda, which writes its output back to that same key), so it
     * can't be reordered to put the filename first just to make this easier. Sizes aren't
     * hardcoded here as a result: a previous version guessed at the current set of sizes and
     * silently went stale the first time a template's image size changed. Listing the
     * destination bucket's top-level "folders" (S3's Delimiter option on ListObjectsV2)
     * discovers whatever sizes actually exist right now instead.
     */
    public function removeCache(Image $image): void
    {
        $name = pathinfo($image->getFilename(), \PATHINFO_FILENAME);

        $sizes = [];
        $paginator = $this->s3Client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->s3CacheBucket,
            'Delimiter' => '/',
        ]);
        foreach ($paginator->search('CommonPrefixes[].Prefix') as $prefix) {
            $sizes[] = rtrim((string) $prefix, '/');
        }

        if ([] === $sizes) {
            return;
        }

        $objects = [];
        foreach ($sizes as $size) {
            foreach (self::CACHED_FORMATS as $format) {
                $objects[] = ['Key' => "{$size}/{$format}/{$name}.{$format}"];
            }
        }

        $this->s3Client->deleteObjects([
            'Bucket' => $this->s3CacheBucket,
            'Delete' => ['Objects' => $objects],
        ]);
    }

    /**
     * Patch the original S3 object's metadata with the GenAI-detected focal point, for the
     * captain-infra crop Lambda to read (it has no DB access -- see AGENTS.md). Flysystem's
     * write() only sets metadata at upload time; S3 object metadata is otherwise immutable in
     * place, so this needs a direct CopyObject call (same key, MetadataDirective=REPLACE) via
     * the AWS SDK. REPLACE overwrites *all* metadata, not merges it -- the existing watermark
     * value must be re-supplied here too, or it would be silently dropped. Same for Content-Type,
     * which would otherwise fall back to binary/octet-stream.
     *
     * Throws on failure: the v2 URL hashes the DB values and the Lambda checks them against this
     * metadata, so a DB committed without it would answer 409 -- callers must not flush then.
     */
    public function writeFocalPointMetadata(Image $image): void
    {
        $key = $image->getFilename();

        $this->s3Client->copyObject([
            'Bucket' => $this->s3OriginalBucket,
            'Key' => $key,
            'CopySource' => rawurlencode("{$this->s3OriginalBucket}/{$key}"),
            'MetadataDirective' => 'REPLACE',
            'ContentType' => Image::MIME_TYPE,
            'Metadata' => [
                'watermark' => $image->isWatermarked() ? '1' : '0',
                'focal-x' => (string) $image->getFocalX(),
                'focal-y' => (string) $image->getFocalY(),
            ],
        ]);
    }

    /** Update main image property of all coasters. */
    public function setMainImages(): void
    {
        $conn = $this->em->getConnection();

        $sql = 'UPDATE coaster c
            LEFT JOIN (
                SELECT DISTINCT coaster_id,
                       FIRST_VALUE(id) OVER (PARTITION BY coaster_id ORDER BY like_counter DESC, updated_at DESC) as id
                FROM image
                WHERE enabled = 1
            ) i ON i.coaster_id = c.id
            SET c.main_image_id = i.id';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /** Update like counters for all images. */
    public function updateLikeCounters(): void
    {
        $conn = $this->em->getConnection();

        $sql = 'UPDATE image i
            LEFT JOIN (
                SELECT image_id, COUNT(*) as nb
                FROM liked_image
                GROUP BY image_id
            ) li ON i.id = li.image_id
            SET i.like_counter = COALESCE(li.nb, 0)';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->executeStatement();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}

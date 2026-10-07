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
            ['Metadata' => self::originalMetadata($image), 'ContentType' => Image::MIME_TYPE]
        );

        return $filename;
    }

    /** An image with the exact same bytes, or null. */
    public function isDuplicate(UploadedFile $file): ?Image
    {
        $content = file_get_contents($file->getPathname());
        if (false === $content) {
            return null;
        }

        return $this->imageRepository->findOneBy(['hash' => hash('sha256', $content)]);
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
     * The S3 metadata of a photo's original, all of it, from the entity: the DB is the golden
     * source and the captain-infra Lambda (no DB access) reads these to crop, watermark and check
     * the v2 URL hash. Everything that writes an original takes its metadata from here, so a
     * write can never drop a key another one set. An unset focal point or rev has no key (the
     * Lambda reads a missing one as "none", like PictureUrlSigner does for null).
     *
     * @return array<string, string>
     */
    public static function originalMetadata(Image $image): array
    {
        $metadata = ['watermark' => $image->isWatermarked() ? '1' : '0'];
        if (null !== $image->getFocalX() && null !== $image->getFocalY()) {
            $metadata['focal-x'] = (string) $image->getFocalX();
            $metadata['focal-y'] = (string) $image->getFocalY();
        }
        if (null !== $image->getRev()) {
            $metadata['rev'] = (string) $image->getRev();
        }

        return $metadata;
    }

    /**
     * Rewrite the original's S3 metadata from the entity, e.g. after a new focal point. S3
     * metadata is immutable in place: this is a CopyObject onto the same key with
     * MetadataDirective=REPLACE, which replaces the whole set (and the Content-Type, re-supplied
     * or it falls back to binary/octet-stream) -- hence originalMetadata(), never a partial list.
     *
     * Throws on failure: the v2 URL hashes the DB values and the Lambda checks them against this
     * metadata, so a DB committed without it would answer 409 -- callers must not flush then.
     */
    public function syncOriginalMetadata(Image $image): void
    {
        $key = $image->getFilename();

        $this->s3Client->copyObject([
            'Bucket' => $this->s3OriginalBucket,
            'Key' => $key,
            'CopySource' => rawurlencode("{$this->s3OriginalBucket}/{$key}"),
            'MetadataDirective' => 'REPLACE',
            'ContentType' => Image::MIME_TYPE,
            'Metadata' => self::originalMetadata($image),
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

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Image;
use App\Repository\ImageRepository;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly FilesystemOperator $picturesFilesystem,
        private readonly S3Client $s3Client,
        private readonly ImageRepository $imageRepository,
        #[Autowire('%env(string:AWS_S3_CACHE_BUCKET_NAME)%')]
        private readonly string $s3CacheBucket
    ) {
    }

    /** Create file on abstracted filesystem (currently S3). */
    public function upload(Image $image): string
    {
        $filename = $this->generateFilename($image->getFile(), $image->getCoaster()->getSlug());

        $this->picturesFilesystem->write(
            $filename,
            $image->getFile()->getContent(),
            ['Metadata' => ['watermark' => $image->isWatermarked() ? '1' : '0']]
        );

        return $filename;
    }

    /** Check if image already exists based on file hash. */
    public function isDuplicate(UploadedFile $file): ?Image
    {
        $content = file_get_contents($file->getPathname());
        if (false === $content) {
            return null;
        }
        $hash = dechex(crc32($content));

        return $this->imageRepository->findOneBy(['hash' => $hash]);
    }

    /** Calculate and set hash for image. */
    public function setImageHash(Image $image): void
    {
        if ($image->getFile()) {
            $content = file_get_contents($image->getFile()->getPathname());
            if (false !== $content) {
                $hash = dechex(crc32($content));
                $image->setHash($hash);
            }
        }
    }

    /** Remove file from abstracted filesystem (currently S3). */
    public function remove(string $filename): void
    {
        $this->picturesFilesystem->delete($filename);
    }

    /** Remove file from S3 Cache Bucket. */
    public function removeCache(Image $image): void
    {
        $this->s3Client->deleteObjects([
            'Bucket' => $this->s3CacheBucket,
            'Delete' => [
                'Objects' => [
                    ['Key' => '1440x1440/'.$image->getFilename()],
                    ['Key' => '600x336/'.$image->getFilename()],
                    ['Key' => '280x210/'.$image->getFilename()],
                    ['Key' => '96x96/'.$image->getFilename()],
                ],
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

    /** Generates a filename like fury-325-carowinds-64429c62b6b23.jpg. */
    private function generateFilename(UploadedFile $file, string $coasterSlug): string
    {
        return \sprintf('%s-%s.%s', $coasterSlug, uniqid(), $file->guessExtension());
    }
}

<?php

namespace BddBundle\Service;

use BddBundle\Entity\Image;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Filter;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\Metadata\ExifMetadataReader;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ImageManager
{
    /**
     * Image max size
     */
    CONST MAX_SIZE = 1440;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var string
     */
    private $watermarkPath;

    /**
     * @var string
     */
    private $jpegoptimPath;

    /**
     * @var Imagine
     */
    private $imagine;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ImageUploader constructor.
     * @param string $basePath
     * @param string $watermarkPath
     * @param string $jpegoptimPath
     * @param Imagine $imagine
     * @param EntityManagerInterface $em
     * @param LoggerInterface $logger
     */
    public function __construct(
        string $basePath,
        string $watermarkPath,
        string $jpegoptimPath,
        Imagine $imagine,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->basePath = $basePath;
        $this->watermarkPath = $watermarkPath;
        $this->jpegoptimPath = $jpegoptimPath;
        $this->imagine = $imagine;
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * Create file on disk
     *
     * @param UploadedFile $file
     * @return string
     * @throws \Exception
     */
    public function upload(UploadedFile $file)
    {
        $filename = $this->generateFilename($file);

        $file->move(
            $this->getFullPath($filename),
            $filename
        );

        $fs = new Filesystem();
        $fs->chmod($this->getFullPath($filename, true), 0660);

        return $filename;
    }

    /**
     * Remove file from disk
     *
     * @param string $filename
     * @return bool
     */
    public function remove(string $filename)
    {
        $file = sprintf('%s/%s', $this->getFullPath($filename), $filename);
        $fs = new Filesystem();

        if ($fs->exists($file)) {
            $fs->remove($file);

            return true;
        }

        return false;
    }

    /**
     * Process a new image
     *
     * @param Image $image
     * @return bool
     */
    public function process(Image $image)
    {
        $this->backupFile($image);

        $this->autoRotate($image);

        $fullPath = $this->getFullPath($image->getFilename(), true);
        $file = $this->imagine->open($fullPath);
        $transformation = new Filter\Transformation();
        $newSize = $this->getResizedBox($file->getSize());
        $transformation->add(new Filter\Basic\Resize($newSize));

        $paste = $this->getPasteWatermark($image, $newSize);
        if ($paste instanceof Filter\Basic\Paste) {
            $transformation->add($paste);
        }

        try {
            $transformation->apply($file)->save($fullPath, ['jpeg_quality' => 80]);
            $this->optimize($image);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());

            return false;
        }

        $this->setOptimized($image);

        return true;
    }

    /**
     * Update main image property of all coasters
     * @todo faire mieux :)
     */
    public function setMainImages()
    {
        $conn = $this->em->getConnection();

        $sql = 'update coaster c
          inner join
          (
            select id, coaster_id from image i 
            where enabled = 1 
            order by updated_at asc, id asc
          ) as i2 on i2.coaster_id = c.id
          set c.main_image_id = i2.id;';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } catch (DBALException $e) {
            // do nothing
        }
    }

    /**
     * @param Image $image
     * @return bool
     */
    private function autoRotate(Image $image)
    {
        $fullPath = $this->getFullPath($image->getFilename(), true);
        $file = $this->imagine->setMetadataReader(new ExifMetadataReader())->open($fullPath);
        $transformation = new Filter\Transformation();
        $transformation->add(new Filter\Basic\Autorotate());

        try {
            $transformation->apply($file)->save($fullPath, ['jpeg_quality' => 80]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param Image $image
     */
    private function setOptimized(Image $image)
    {
        $image->setOptimized(true);
        $this->em->persist($image);
        $this->em->flush();
    }

    /**
     * @param Image $image
     */
    private function backupFile(Image $image)
    {
        $fs = new Filesystem();
        $fs->copy(
            $this->getFullPath($image->getFilename(), true),
            $this->getFullBackupPath($image->getFilename(), true),
            true
        );
    }

    /**
     * @param BoxInterface $size
     * @param int $maxSize
     * @return Box|BoxInterface
     */
    private function getResizedBox(BoxInterface $size, int $maxSize = self::MAX_SIZE)
    {
        $width = $size->getWidth();
        $height = $size->getHeight();

        if ($width <= $maxSize || $height <= $maxSize) {
            return $size;
        }

        if ($width > $height) {
            return new Box($maxSize, $height * $maxSize / $width);
        } else {
            return new Box($width * $maxSize / $height, $maxSize);
        }
    }

    /**
     * @param Image $image
     * @param BoxInterface $size
     * @return bool|Filter\Basic\Paste
     */
    private function getPasteWatermark(Image $image, BoxInterface $size)
    {
        if (!$image->isWatermarked()) {
            return false;
        }

        $watermark = $this->imagine->open($this->watermarkPath);
        $wSize = $watermark->getSize();

        $bottomLeft = new Point(30, $size->getHeight() - $wSize->getHeight() - 30);

        return new Filter\Basic\Paste($watermark, $bottomLeft);
    }

    /**
     * @param Image $image
     * @return bool
     */
    private function optimize(Image $image)
    {
        $fullPath = $this->getFullPath($image->getFilename(), true);
        $backupFullPath = $this->getFullBackupPath($image->getFilename(), true);
        $process = new Process("$this->jpegoptimPath -s $fullPath $backupFullPath");
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return true;
    }

    /**
     * Get full path like /var/www/image/8f52b371-1c2d-4a08-95f7-48cff34a1fc6.jpeg
     *
     * @param string $filename
     * @param bool $includeFilename
     * @return string
     */
    private function getFullPath(string $filename, bool $includeFilename = false): string
    {
        $path = sprintf('%s/%s', $this->basePath, substr($filename, 0, 1));

        if ($includeFilename) {
            return sprintf('%s/%s', $path, $filename);
        }

        return $path;
    }

    /**
     * Get full backup path like /var/www/image/8f52b371-1c2d-4a08-95f7-48cff34a1fc6.jpeg
     *
     * @param string $filename
     * @param bool $includeFilename
     * @return string
     *
     * @todo faire mieux
     */
    private function getFullBackupPath(string $filename, bool $includeFilename = false): string
    {
        $path = sprintf('%s/backup/%s', $this->basePath, substr($filename, 0, 1));

        if ($includeFilename) {
            return sprintf('%s/%s', $path, $filename);
        }

        return $path;
    }

    /**
     * Generates a filename like 8f52b371-1c2d-4a08-95f7-48cff34a1fc6.jpeg
     *
     * @param UploadedFile $file
     * @return string
     * @throws \Exception
     */
    private function generateFilename(UploadedFile $file): string
    {
        return sprintf('%s.%s', Uuid::uuid4()->toString(), $file->guessExtension());
    }
}

<?php

namespace BddBundle\Service;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageManager
{

    /**
     * @var string
     */
    private $basePath;

    /**
     * ImageUploader constructor.
     * @param $basePath
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    public function upload(UploadedFile $file)
    {
        $filename = $this->generateFilename($file);

        $file->move(
            $this->getFullPath($filename),
            $filename
        );

        return $filename;
    }

    /**
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
     * @param string $filename
     * @return string
     */
    private function getFullPath(string $filename): string
    {
        return sprintf('%s/%s', $this->basePath, substr($filename, 0, 1));
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    private function generateFilename(UploadedFile $file): string
    {
        return sprintf('%s.%s', Uuid::uuid4()->toString(), $file->guessExtension());
    }
}

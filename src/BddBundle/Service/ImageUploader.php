<?php

namespace BddBundle\Service;

use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploader
{

    private $basePath;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    public function upload(UploadedFile $file)
    {
        $filename = sprintf('%s.%s', Uuid::uuid4()->toString(), $file->guessExtension());

        $file->move(
            sprintf('%s/%s', $this->basePath, substr($filename, 0, 1)),
            $filename
        );

        return $filename;
    }
}

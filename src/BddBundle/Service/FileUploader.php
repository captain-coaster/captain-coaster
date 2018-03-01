<?php

namespace BddBundle\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

abstract class FileUploader
{
    /**
     * @var string
     */
    private $targetDir;

    /**
     * FileUploader constructor.
     * @param string $targetDir
     */
    public function __construct(string $targetDir)
    {
        $this->targetDir = $targetDir;
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    public function upload(UploadedFile $file)
    {
        $fileName = $this->getFileName($file);

        $file->move($this->getTargetDir(), $fileName);

        return $fileName;
    }

    /**
     * @return string
     */
    public function getTargetDir()
    {
        return $this->targetDir;
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    private function getFileName(UploadedFile $file)
    {
        return md5(uniqid()).'.'.$file->guessExtension();
    }
}

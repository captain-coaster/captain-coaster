<?php

namespace BddBundle\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ReportImageUploader extends FileUploader
{
    /**
     * @var ImageManipulationService
     */
    private $imageManipulator;

    /**
     * @param string $targetDir
     * @param ImageManipulationService $imageManipulator
     */
    public function construct(string $targetDir, ImageManipulationService $imageManipulator)
    {
        parent::__construct($targetDir);
        $this->imageManipulator = $imageManipulator;
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    public function upload(UploadedFile $file)
    {
        $fileName = parent::upload($file);

        $this->imageManipulator->resizeLongSide($this->getTargetDir() . '/' . $fileName, 800);

        return $fileName;
    }
}

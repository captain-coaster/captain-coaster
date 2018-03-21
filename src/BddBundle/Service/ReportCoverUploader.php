<?php

namespace BddBundle\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ReportCoverUploader extends FileUploader
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

        $this->imageManipulator->resizeLongSide($this->getTargetDir() . '/' . $fileName, 2000);

        return $fileName;
    }
}

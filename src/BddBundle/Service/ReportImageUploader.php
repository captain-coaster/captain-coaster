<?php

namespace BddBundle\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ReportImageUploader extends FileUploader
{
    /**
     * @param string $targetDir
     * @param ImageManipulationService $imageManipulator
     */
    public function construct(string $targetDir, ImageManipulationService $imageManipulator)
    {
        parent::__construct($targetDir, $imageManipulator);
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

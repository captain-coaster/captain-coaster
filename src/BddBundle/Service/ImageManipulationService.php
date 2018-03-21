<?php

namespace BddBundle\Service;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;

class ImageManipulationService
{
    private $imagine;

    public function __construct(Imagine $imagine)
    {
        $this->imagine = $imagine;
    }

    public function resizeLongSide($path, $maxSize = 2000)
    {
        $image = $this->imagine->open($path);
        $height = $image->getSize()->getHeight();
        $width = $image->getSize()->getWidth();

        if ($width > $height) {
            $ratio = $maxSize / $width;
            $box = new Box($maxSize, $height * $ratio);
        } else {
            $ratio = $maxSize / $height;
            $box = new Box($width * $ratio, $height);
        }

        $image
            ->thumbnail($box)
            ->save($path);
    }
}

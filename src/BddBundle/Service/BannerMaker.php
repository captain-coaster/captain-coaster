<?php

namespace BddBundle\Service;

use BddBundle\Entity\ListeCoaster;
use BddBundle\Entity\User;
use Imagine\Exception\InvalidArgumentException;
use Imagine\Image\Point;
use Imagine\Imagick\Image;
use Imagine\Imagick\Imagine;

class BannerMaker
{
    /**
     * @var Imagine
     */
    private $imagine;

    /**
     * @var string path to font
     */
    private $fontPath;

    /**
     * @var string path target directory
     */
    private $targetPath;

    /**
     * @var string path to background image
     */
    private $backgroundPath;

    /**
     * @var Image
     */
    private $image;

    /**
     * BannerMaker constructor.
     * @param Imagine $imagine
     * @param $fontPath
     * @param $targetPath
     * @param $backgroundPath
     */
    public function __construct(Imagine $imagine, $fontPath, $targetPath, $backgroundPath)
    {
        $this->imagine = $imagine;

        $this->fontPath = $fontPath;
        $this->targetPath = $targetPath;
        $this->backgroundPath = $backgroundPath;
    }

    /**
     * @param User $user
     * @throws InvalidArgumentException
     */
    public function makeBanner(User $user)
    {
        $this->createImage();
        $this->writeCoasterCount($user->getRatings()->count());

        $top = [];
        /** @var ListeCoaster $listeCoaster */
        foreach ($user->getMainListe()->getListeCoasters()->slice(0, 3) as $listeCoaster) {
            $top[] = $listeCoaster->getCoaster()->getName();
        }

        $this->writeTop3($top);
        $this->saveImage($user);
    }

    /**
     * Create new image with background
     */
    private function createImage()
    {
        $this->image = $this->imagine->open($this->backgroundPath);
    }

    /**
     * @param User $user
     */
    private function saveImage(User $user)
    {
        $this->image->save(sprintf('%s/%d.png', $this->targetPath, $user->getId()));
    }

    /**
     * @param int $count
     * @throws InvalidArgumentException
     */
    private function writeCoasterCount(int $count)
    {
        $this->writeText(sprintf($count.' coasters'), 110, 32, 12);
    }

    /**
     * @param array $top
     * @throws InvalidArgumentException
     */
    private function writeTop3(array $top)
    {
        $y = 3;
        $position = 1;
        foreach ($top as $coaster) {
            $this->writeText(sprintf('%d - %s', $position, $coaster), 240, $y, 10);
            $y = $y + 19;
            $position++;
        }
    }

    /**
     * @param string $text
     * @param $x
     * @param $y
     * @param int $size
     * @param string $color
     * @throws InvalidArgumentException
     */
    private function writeText(string $text, $x, $y, $size = 10, $color = 'FFFFFF')
    {
        if (!$this->image instanceof Image) {
            $this->createImage();
        }

        $color = $this->image->palette()->color($color);
        $font = $this->imagine->font($this->fontPath, $size, $color);


        $this->image->draw()->text($text, $font, new Point($x, $y));
    }
}

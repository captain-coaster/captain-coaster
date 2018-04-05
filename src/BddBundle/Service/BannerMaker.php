<?php

namespace BddBundle\Service;

//use Imagine\Gd\Imagine;
use BddBundle\Entity\User;
use Imagine\Image\Point;
use Imagine\Imagick\Image;
use Imagine\Imagick\Imagine;

class BannerMaker
{
    private $imagine;

    private $fontPath;
    private $targetPath;
    private $backgroundPath;

    /**
     * @var Image
     */
    private $image;

    public function __construct(Imagine $imagine, $fontPath, $targetPath, $backgroundPath)
    {
        $this->imagine = $imagine;

        $this->fontPath = $fontPath;
        $this->targetPath = $targetPath;
        $this->backgroundPath = $backgroundPath;
    }

    public function makeBanner(User $user)
    {
        $this->createImage();
        $this->writeCoasterCount($user->getRatings()->count());

        $top = [];
        foreach($user->getMainListe()->getListeCoasters()->slice(0, 3) as $listeCoaster) {
            $top[] = $listeCoaster->getCoaster()->getName();
        }

        $this->writeTop3($top);
        $this->saveImage($user);
    }

    private function createImage()
    {
        $this->image = $this->imagine->open($this->backgroundPath);
    }

    private function saveImage(User $user)
    {
        $this->image->save(sprintf('%s/%d.png', $this->targetPath, $user->getId()));
    }

    private function writeCoasterCount(int $count)
    {
        $this->writeText(sprintf($count.' coasters'), 110, 32, 12);
    }

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
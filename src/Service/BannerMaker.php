<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TopCoaster;
use App\Entity\User;
use Imagine\Exception\InvalidArgumentException;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Imagine\Imagick\Image;
use Imagine\Imagick\Imagine;

class BannerMaker
{
    private ?ImageInterface $image = null;

    public function __construct(
        private readonly Imagine $imagine,
        /** @var string path to font */
        private $fontPath,
        /** @var string path target directory */
        private $targetPath,
        /** @var string path to background image */
        private $backgroundPath
    ) {
    }

    /** @throws InvalidArgumentException */
    public function makeBanner(User $user): void
    {
        $this->createImage();
        $this->writeCoasterCount($user->getRatings()->count());

        $top = [];
        /** @var TopCoaster $topCoaster */
        foreach ($user->getMainTop()->getTopCoasters()->slice(0, 3) as $topCoaster) {
            $top[] = $topCoaster->getCoaster()->getName();
        }

        $this->writeTop3($top);
        $this->saveImage($user);
    }

    /** Create new image with background. */
    private function createImage(): void
    {
        $this->image = $this->imagine->open($this->backgroundPath);
    }

    private function saveImage(User $user): void
    {
        $this->image->save(sprintf('%s/%d.png', $this->targetPath, $user->getId()));
    }

    /** @throws InvalidArgumentException */
    private function writeCoasterCount(int $count): void
    {
        $this->writeText($count.' coasters', 110, 32, 12);
    }

    /** @throws InvalidArgumentException */
    private function writeTop3(array $top): void
    {
        $y = 3;
        $position = 1;
        foreach ($top as $coaster) {
            $this->writeText(sprintf('%d - %s', $position, $coaster), 240, $y, 10);
            $y += 19;
            ++$position;
        }
    }

    /**
     * @param int    $size
     * @param string $color
     *
     * @throws InvalidArgumentException
     */
    private function writeText(string $text, $x, $y, $size = 10, $color = 'FFFFFF'): void
    {
        if (!$this->image instanceof Image) {
            $this->createImage();
        }

        $color = $this->image->palette()->color($color);
        $font = $this->imagine->font($this->fontPath, $size, $color);

        $this->image->draw()->text($text, $font, new Point($x, $y));
    }
}

<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Image;

/**
 * What PictureUrlSigner needs from a photo, detached from Doctrine so it can sit in a cache
 * entry (HeroService) without a lazy-load.
 */
final readonly class PictureRef
{
    public function __construct(
        public int $id,
        public string $filename,
        public ?float $focalX,
        public ?float $focalY,
        public bool $watermarked,
        public string $seo,
    ) {
    }

    public static function fromImage(Image $image): self
    {
        return new self(
            (int) $image->getId(),
            $image->getFilename(),
            $image->getFocalX(),
            $image->getFocalY(),
            $image->isWatermarked(),
            $image->getCoaster()->getSlug() ?? 'photo',
        );
    }
}

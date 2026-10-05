<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Image;

/**
 * What PictureUrlSigner needs from a photo, detached from Doctrine so it can sit in a cache
 * entry (HeroService) without a lazy-load.
 *
 * Every input of the v2 URL hash is listed in this class and nowhere else: the constructor,
 * fromImage(), and SELECT + fromRow() for queries that skip entity hydration.
 *
 * @phpstan-type PictureRow array{id: int, filename: string, focalX: ?float, focalY: ?float, watermarked: bool, rev: ?int, coasterId: int, coasterSlug: ?string}
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
        public ?int $rev = null,
    ) {
    }

    /** DQL select list feeding fromRow(), the image aliased `i` and its coaster `c` (whose id is there for the caller to group by). */
    public const array SELECT = [
        'i.id AS id',
        'i.filename AS filename',
        'i.focalX AS focalX',
        'i.focalY AS focalY',
        'i.watermarked AS watermarked',
        'i.rev AS rev',
        'c.id AS coasterId',
        'c.slug AS coasterSlug',
    ];

    /** @param PictureRow $row */
    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'],
            $row['filename'],
            $row['focalX'],
            $row['focalY'],
            $row['watermarked'],
            $row['coasterSlug'] ?? 'photo',
            $row['rev'],
        );
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
            $image->getRev(),
        );
    }
}

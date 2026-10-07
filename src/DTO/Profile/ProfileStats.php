<?php

declare(strict_types=1);

namespace App\DTO\Profile;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\RiddenCoaster;

/**
 * What a profile page shows about a member. A block's data is empty (0, [], null) when the block doesn't show:
 * the thresholds live in ProfileStatsBuilder.
 */
final readonly class ProfileStats
{
    /**
     * @param list<Coaster>                      $favourites first coasters of the main Top, in order
     * @param list<ProfileRecord>                $records
     * @param list<array{year: int, count: int}> $years      coasters with a ride date, per year, oldest first
     * @param ?RiddenCoaster                     $topReview  their most upvoted review among those long enough to feature
     * @param list<Image>                        $topPhotos  their most liked photo; the two most liked without a review
     */
    public function __construct(
        public int $ridden,
        public int $parks,
        public int $countries,
        public int $year,
        public int $riddenInYear,
        public int $top100,
        public array $favourites,
        public ?int $mainTopId,
        public int $tops,
        public array $records,
        public ?ProfileRatings $ratings,
        public int $reviews,
        public int $upvotes,
        public int $photos,
        public int $likes,
        public array $years,
        public int $undated,
        public ?RiddenCoaster $topReview = null,
        public array $topPhotos = [],
    ) {
    }

    public function hasContributions(): bool
    {
        return $this->reviews > 0 || $this->photos > 0;
    }
}

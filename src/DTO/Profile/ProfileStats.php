<?php

declare(strict_types=1);

namespace App\DTO\Profile;

use App\Entity\Coaster;

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
    ) {
    }

    public function hasContributions(): bool
    {
        return $this->reviews > 0 || $this->photos > 0;
    }
}

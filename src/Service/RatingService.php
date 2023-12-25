<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\RiddenCoasterRepository;

class RatingService
{
    final public const MIN_RATINGS = 2;

    public function __construct(private readonly RiddenCoasterRepository $riddenCoasterRepository)
    {
    }

    /** Update averageRating for all coasters. */
    public function updateRatings(): int
    {
        $this->riddenCoasterRepository->updateTotalRatings();

        return $this->riddenCoasterRepository->updateAverageRatings(self::MIN_RATINGS);
    }
}

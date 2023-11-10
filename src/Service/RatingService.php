<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Class RatingService.
 */
class RatingService
{
    final public const MIN_RATINGS = 2;

    /**
     * RatingService constructor.
     */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Update averageRating for all coasters.
     */
    public function updateRatings(): int
    {
        $repo = $this->em->getRepository('App:RiddenCoaster');

        $repo->updateTotalRatings();

        return $repo->updateAverageRatings(self::MIN_RATINGS);
    }
}

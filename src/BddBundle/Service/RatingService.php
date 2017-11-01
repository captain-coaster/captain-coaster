<?php

namespace BddBundle\Service;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RiddenCoaster;
use Doctrine\ORM\EntityManager;

/**
 * Class RatingService
 * @package BddBundle\Service
 */
class RatingService
{
    const MIN_RATINGS = 2;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * RatingService constructor.
     *
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Update averageRating for a coaster
     *
     * @param Coaster $coaster
     * @return string
     */
    public function manageRatings(Coaster $coaster)
    {
        /** @var RiddenCoaster[] $ratings */
        $ratings = $coaster->getRatings();

        $averageRating = null;
        $totalRatings = count($ratings);

        // Update average value only if we have enough ratings
        if (count($ratings) >= self::MIN_RATINGS) {
            $sum = 0;
            foreach ($ratings as $rating) {
                $sum += (float)$rating->getValue();
            }

            $averageRating = number_format(round(($sum / $totalRatings), 3), 3);
        }

        $coaster->setAverageRating($averageRating);
        $coaster->setTotalRatings($totalRatings);
        $this->em->persist($coaster);
        $this->em->flush();

        return $coaster->getAverageRating();
    }
}
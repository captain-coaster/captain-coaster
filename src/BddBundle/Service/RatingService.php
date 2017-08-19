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
    const MIN_RATINGS = 8;

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
     * Updates averageRating for a coaster
     *
     * @param Coaster $coaster
     * @return string
     */
    public function manageRatings(Coaster $coaster)
    {
        /** @var RiddenCoaster[] $ratings */
        $ratings = $coaster->getRatings();

        if (count($ratings) < self::MIN_RATINGS) {
            return $coaster->getAverageRating();
        }

        $total = 0;
        foreach ($ratings as $rating) {
            $total += (float) $rating->getValue();
        }

        $score = $total / count($ratings);
        $score = round($score, 3);
        $score = number_format($score, 3);

        $coaster->setAverageRating($score);
        $coaster->setTotalRatings(count($ratings));
        $this->em->persist($coaster);
        $this->em->flush();

        return $coaster->getAverageRating();
    }
}
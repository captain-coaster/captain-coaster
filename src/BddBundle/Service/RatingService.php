<?php

namespace BddBundle\Service;

use BddBundle\Entity\Coaster;
use BddBundle\Entity\RatingCoaster;
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

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function manageRatings(Coaster $coaster)
    {
        /** @var RatingCoaster[] $ratings */
        $ratings = $coaster->getRatings();

        if (count($ratings) < self::MIN_RATINGS) {
            return;
        }

        $total = 0;
        foreach ($ratings as $rating) {
            $total += (float) $rating->getValue();
        }

        $score = $total / count($ratings);

        $coaster->setAverageRating($score);
        $coaster->setTotalRatings(count($ratings));
        $this->em->persist($coaster);
        $this->em->flush();
    }
}
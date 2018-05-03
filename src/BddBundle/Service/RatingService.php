<?php

namespace BddBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Class RatingService
 * @package BddBundle\Service
 */
class RatingService
{
    const MIN_RATINGS = 2;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * RatingService constructor.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Update averageRating for all coasters
     *
     * @return int
     */
    public function updateRatings(): int
    {
        $repo = $this->em->getRepository('BddBundle:RiddenCoaster');

        $repo->updateTotalRatings();

        return $repo->updateAverageRatings(self::MIN_RATINGS);
    }
}

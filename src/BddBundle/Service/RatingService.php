<?php

namespace BddBundle\Service;

use BddBundle\Entity\Coaster;
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
     * Update averageRating for a coaster
     *
     * @param Coaster $coaster
     * @return string
     */
    public function manageRatings(Coaster $coaster)
    {
        $repo = $this->em->getRepository('BddBundle:RiddenCoaster');

        if ($repo->countForCoaster($coaster) >= self::MIN_RATINGS) {
            $repo->updateAverageRating($coaster);
            $this->em->refresh($coaster);
        }

        $repo->updateTotalRating($coaster);

        return $coaster->getAverageRating();
    }
}

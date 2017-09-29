<?php

namespace BddBundle\Service;

use Doctrine\ORM\EntityManager;

/**
 * Class StatService
 * @package BddBundle\Service
 */
class StatService
{
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

    public function getIndexStats()
    {
        $stats = [];

        $stats['nb_coasters'] = $this->em
            ->getRepository('BddBundle:Coaster')
            ->countAll();

        $stats['nb_ratings'] = $this->em
            ->getRepository('BddBundle:RiddenCoaster')
            ->countAll();

        $date = new \DateTime();
        $date->sub(new \DateInterval('P1D'));
        $stats['nb_new_ratings'] = $this->em
            ->getRepository('BddBundle:RiddenCoaster')
            ->countNew($date);

        $stats['nb_reviews'] = $this->em
            ->getRepository('BddBundle:RiddenCoaster')
            ->countReviews();

        $stats['nb_listes'] = $this->em
            ->getRepository('BddBundle:Liste')
            ->countAll();

        return $stats;
    }
}
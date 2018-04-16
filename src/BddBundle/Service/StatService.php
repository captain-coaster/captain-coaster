<?php

namespace BddBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Class StatService
 * @package BddBundle\Service
 */
class StatService
{
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
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
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

<?php

namespace BddBundle\Service;

use BddBundle\Entity\User;
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

    /**
     * @param $user
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getUserStats(User $user)
    {
        $stats = [];

        if ($this->em->getRepository('BddBundle:RiddenCoaster')->countForUser($user) === '0') {
            return $stats;
        }

        $stats['nb_coasters'] = $this->em
            ->getRepository('BddBundle:RiddenCoaster')
            ->countForUser($user);
        $stats['nb_park'] = $this->em
            ->getRepository('BddBundle:Park')
            ->countForUser($user);
        $stats['nb_country'] = $this->em
            ->getRepository('BddBundle:Country')
            ->countForUser($user);
        $stats['country'] = $this->em
            ->getRepository('BddBundle:RiddenCoaster')
            ->findMostRiddenCountry($user);

        return $stats;
    }
}

<?php

namespace App\Service;

use App\Entity\Country;
use App\Entity\Image;
use App\Entity\TopCoaster;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class StatService
 * @package App\Service
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
     * @throws \Exception
     */
    public function getIndexStats()
    {
        $stats = [];

        $stats['nb_ratings'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countAll();

        $date = new \DateTime();
        $date->sub(new \DateInterval('P1D'));
        $stats['nb_new_ratings'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countNew($date);

        $stats['nb_reviews'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countReviews();

        $stats['nb_users'] = $this->em
            ->getRepository(User::class)
            ->countAll();

        $stats['nb_images'] = $this->em
            ->getRepository(Image::class)
            ->countAll();

        return $stats;
    }

    /**
     * @param $user
     * @return array
     */
    public function getUserStats(User $user)
    {
        $stats = [];

        if ($this->em->getRepository(RiddenCoaster::class)->countForUser($user) === '0') {
            return $stats;
        }

        $stats['nb_coasters'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countForUser($user);
        $stats['nb_park'] = $this->em
            ->getRepository(Park::class)
            ->countForUser($user);
        $stats['nb_country'] = $this->em
            ->getRepository(Country::class)
            ->countForUser($user);
        $stats['country'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->findMostRiddenCountry($user);
        $stats['top_100'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countTop100ForUser($user);
        $stats['manufacturer'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->getMostRiddenManufacturer($user);

        return $stats;
    }
}

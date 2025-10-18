<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\Image;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class StatService.
 */
class StatService
{
    /** RatingService constructor. */
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return array
     *
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

    /** @return array */
    public function getUserStats(User $user)
    {
        $stats = [];

        if ('0' === $this->em->getRepository(RiddenCoaster::class)->countForUser($user)) {
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
        $top100 = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countTop100ForUser($user);
        $stats['top_100'] = $top100['nb_top100'];
        $stats['top_100_operating'] = (int) $top100['nb_top100_operating'];
        $stats['manufacturer'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->getMostRiddenManufacturer($user);

        // Add favorite manufacturer from user's main top list (first 10-20 positions)
        $stats['top_rated_manufacturer'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->getTopListManufacturer($user, 10);

        return $stats;
    }
}

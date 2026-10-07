<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A member's figures on their profile.
 */
class StatService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VocabularyLabeler $labeler,
    ) {
    }

    /** @return array<string, mixed> */
    public function getUserStats(User $user): array
    {
        $stats = [];

        $nbCoasters = $this->em->getRepository(RiddenCoaster::class)->countForUser($user);
        if (0 === $nbCoasters) {
            return $stats;
        }

        $stats['nb_coasters'] = $nbCoasters;
        $stats['nb_park'] = $this->em
            ->getRepository(Park::class)
            ->countForUser($user);
        $stats['nb_country'] = $this->em
            ->getRepository(Country::class)
            ->countForUser($user);
        $country = $this->em
            ->getRepository(RiddenCoaster::class)
            ->findMostRiddenCountry($user);
        $stats['country'] = ['name' => $this->labeler->country($country['code'], $country['name']), 'nb' => $country['nb']];
        $top100 = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countTop100ForUser($user);

        $stats['top_100'] = $top100['nb_top100'];
        $stats['top_100_operating'] = $top100['nb_top100_operating'];

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

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\Image;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Class StatService.
 */
class StatService
{
    /** RatingService constructor. */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function getIndexStats(): array
    {
        $stats = [];

        $stats['nb_ratings'] = $this->em
            ->getRepository(RiddenCoaster::class)
            ->countAll();

        // Cache under a fixed key so the "now - 1 day" boundary computed
        // inside the callback doesn't itself defeat caching -- it only
        // runs on a real cache miss, once per TTL.
        $stats['nb_new_ratings'] = $this->cache->get('stats_nb_new_ratings', function (ItemInterface $item) {
            $item->expiresAfter(600);

            return $this->em
                ->getRepository(RiddenCoaster::class)
                ->countNew(new \DateTime('-1 day'));
        });

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

    /** @return array<string, mixed> */
    public function getUserStats(User $user): array
    {
        $stats = [];

        if (0 === $this->em->getRepository(RiddenCoaster::class)->countForUser($user)) {
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

        if (\is_array($top100)) {
            $stats['top_100'] = $top100['nb_top100'];
            $stats['top_100_operating'] = (int) $top100['nb_top100_operating'];
        } else {
            $stats['top_100'] = 0;
            $stats['top_100_operating'] = 0;
        }

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

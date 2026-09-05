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
     * Homepage stats are cached here, at the display layer, rather than in
     * the repositories themselves: RankingService also calls
     * RiddenCoasterRepository::countAll() to persist the monthly Ranking
     * snapshot, and that write needs the real count, not a value that could
     * be up to 10 minutes stale.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function getIndexStats(): array
    {
        $riddenCoasterRepository = $this->em->getRepository(RiddenCoaster::class);

        return [
            'nb_ratings' => $this->cachedCount('stats_nb_ratings', static fn () => $riddenCoasterRepository->countAll()),
            'nb_new_ratings' => $this->cachedCount('stats_nb_new_ratings', static fn () => $riddenCoasterRepository->countNew(new \DateTime('-1 day'))),
            'nb_reviews' => $this->cachedCount('stats_nb_reviews', static fn () => $riddenCoasterRepository->countReviews()),
            'nb_users' => $this->cachedCount('stats_nb_users', fn () => $this->em->getRepository(User::class)->countAll()),
            'nb_images' => $this->cachedCount('stats_nb_images', fn () => $this->em->getRepository(Image::class)->countAll()),
        ];
    }

    private function cachedCount(string $key, callable $count): int
    {
        return $this->cache->get($key, static function (ItemInterface $item) use ($count) {
            $item->expiresAfter(600);

            return $count();
        });
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

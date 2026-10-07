<?php

declare(strict_types=1);

namespace App\Service\Home;

use App\Repository\RiddenCoasterRepository;
use App\Repository\UserRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The community's figures on Home. Cached here rather than in the repositories: RankingService also calls
 * RiddenCoasterRepository::countAll() for the monthly Ranking snapshot, and that write needs the real count.
 */
class CommunityFigures
{
    private const int TTL = 600;

    public function __construct(
        private readonly RiddenCoasterRepository $riddenCoasterRepository,
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @return array{ratings: int, newRatings: int, reviews: int, riders: int} */
    public function get(): array
    {
        return [
            'ratings' => $this->cached('stats_nb_ratings', fn () => $this->riddenCoasterRepository->countAll()),
            'newRatings' => $this->cached('stats_nb_new_ratings', fn () => $this->riddenCoasterRepository->countNew(new \DateTime('-1 day'))),
            'reviews' => $this->cached('stats_nb_reviews', fn () => $this->riddenCoasterRepository->countReviews()),
            'riders' => $this->cached('stats_nb_users', fn () => $this->userRepository->countAll()),
        ];
    }

    /** @param callable(): int $count */
    private function cached(string $key, callable $count): int
    {
        return $this->cache->get($key, static function (ItemInterface $item) use ($count): int {
            $item->expiresAfter(self::TTL);

            return $count();
        });
    }
}

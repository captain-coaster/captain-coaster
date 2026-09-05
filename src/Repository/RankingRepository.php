<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Ranking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ranking>
 */
class RankingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ranking::class);
    }

    public function findCurrent(): ?Ranking
    {
        return $this->fetchRanking(0, 'ranking_current');
    }

    /** @return mixed|null */
    public function findPrevious()
    {
        return $this->fetchRanking(1, 'ranking_previous');
    }

    /**
     * Cleared explicitly by RankingCacheSubscriber when a new ranking is
     * computed -- the long TTL below is only a backstop for whenever that
     * doesn't happen (e.g. a manual DB edit).
     */
    public function clearCache(): void
    {
        $resultCache = $this->getEntityManager()->getConfiguration()->getResultCache();
        $resultCache?->deleteItem('ranking_current');
        $resultCache?->deleteItem('ranking_previous');
    }

    /**
     * Uses enableResultCache() with an explicit id (rather than a generic
     * CacheInterface, as StatService does for its display-only counters)
     * because the returned entity is used as a Doctrine association target
     * by RankingHistoryManagerCommand ($rankingHistory->setRanking(...)) --
     * it must stay a managed entity Doctrine recognizes on flush(), not a
     * detached copy reconstructed from a generic cache's serialized value.
     */
    private function fetchRanking(int $offset, string $cacheId): ?Ranking
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('r')
                ->from(Ranking::class, 'r')
                ->orderBy('r.computedAt', 'desc')
                ->setMaxResults(1)
                ->setFirstResult($offset)
                ->getQuery();

            $query->enableResultCache(604800, $cacheId);

            return $query->getSingleResult();
        } catch (NoResultException|NonUniqueResultException) {
            return null;
        }
    }

    /**
     * @return Query<mixed, mixed>
     *
     * @throws \Exception
     */
    public function findCoastersRanked(): Query
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'p', 'm')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.park', 'p')
            ->innerJoin('c.status', 's')
            ->leftJoin('c.manufacturer', 'm')
            ->leftJoin('p.country', 'country')
            ->leftJoin('country.continent', 'continent')
            ->leftJoin('c.materialType', 'mt')
            ->leftJoin('c.seatingType', 'st')
            ->leftJoin('c.model', 'model')
            ->where('c.rank is not null')
            ->orderBy('c.rank', 'asc');

        return $qb->getQuery();
    }
}

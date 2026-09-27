<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Ranking;
use App\Entity\RankingHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    /** The published ranking on the site. */
    public function findCurrent(): ?Ranking
    {
        return $this->fetchRanking(0, 'ranking_current');
    }

    public function findPrevious(): ?Ranking
    {
        return $this->fetchRanking(1, 'ranking_previous');
    }

    /** The last published ranking before $month. */
    public function findPublishedBefore(\DateTimeImmutable $month): ?Ranking
    {
        return $this->createQueryBuilder('r')
            ->where('r.publishedAt IS NOT NULL')
            ->andWhere('r.month < :month')
            ->setParameter('month', $month, Types::DATE_IMMUTABLE)
            ->orderBy('r.month', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** The oldest ranking computed but not published yet. */
    public function findPending(): ?Ranking
    {
        return $this->createQueryBuilder('r')
            ->where('r.publishedAt IS NULL')
            ->orderBy('r.month', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, int> coaster id => rank */
    public function findRanks(Ranking $ranking): array
    {
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(h.coaster) AS coaster', 'h.rank')
            ->from(RankingHistory::class, 'h')
            ->where('h.ranking = :ranking')
            ->setParameter('ranking', $ranking)
            ->getQuery()
            ->getScalarResult();

        return array_combine(array_map(intval(...), array_column($rows, 'coaster')), array_map(intval(...), array_column($rows, 'rank')));
    }

    /**
     * Cleared by RankingCacheSubscriber when a ranking is published -- the long TTLs are only a backstop for
     * whenever that doesn't happen (e.g. a manual DB edit). The whole result cache goes: other cached queries hold
     * ranks too (the ranking page, the top-100 meter), and it happens once a month.
     */
    public function clearCache(): void
    {
        $this->getEntityManager()->getConfiguration()->getResultCache()?->clear();
    }

    /**
     * Monthly totals of every ranking, oldest first.
     *
     * @return list<array{month: \DateTimeImmutable, ratingNumber: int, userNumber: int, rankedCoasterNumber: int, comparisonNumber: int}>
     */
    public function findTotalsHistory(): array
    {
        /** @var list<array{month: \DateTimeImmutable, ratingNumber: int, userNumber: int, rankedCoasterNumber: int, comparisonNumber: int}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('r.month', 'r.ratingNumber', 'r.userNumber', 'r.rankedCoasterNumber', 'r.comparisonNumber')
            ->where('r.publishedAt IS NOT NULL')
            ->orderBy('r.month', 'ASC')
            ->getQuery()
            ->enableResultCache(604800, 'ranking_history')
            ->getArrayResult();

        return $rows;
    }

    /**
     * Uses enableResultCache() with an explicit id so that clearCache() can target it, and so that a hit still
     * returns a managed entity rather than a copy unserialized from a generic cache.
     */
    private function fetchRanking(int $offset, string $cacheId): ?Ranking
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('r')
                ->from(Ranking::class, 'r')
                ->where('r.publishedAt IS NOT NULL')
                ->orderBy('r.month', 'desc')
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

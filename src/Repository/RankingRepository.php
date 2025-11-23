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
 * RankingRepository.
 */
class RankingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ranking::class);
    }

    public function findCurrent()
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('r')
                ->from(Ranking::class, 'r')
                ->orderBy('r.computedAt', 'desc')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException|NonUniqueResultException) {
            return null;
        }
    }

    /** @return mixed|null */
    public function findPrevious()
    {
        try {
            return $this->getEntityManager()
                ->createQueryBuilder()
                ->select('r')
                ->from(Ranking::class, 'r')
                ->orderBy('r.computedAt', 'desc')
                ->setMaxResults(1)
                ->setFirstResult(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException|NonUniqueResultException) {
            return null;
        }
    }

    /**
     * @return Query
     *
     * @throws \Exception
     */
    public function findCoastersRanked()
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

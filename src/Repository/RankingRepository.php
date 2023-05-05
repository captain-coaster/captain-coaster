<?php

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Ranking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * RankingRepository
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

    /**
     * @return mixed|null
     */
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
     * @return \Doctrine\ORM\Query
     * @throws \Exception
     */
    public function findCoastersRanked(array $filters = [])
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'p', 'm')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.park', 'p')
            ->leftJoin('c.manufacturer', 'm')
            ->where('c.rank is not null');

        $qb->orderBy('c.rank', 'asc');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery();
    }

    private function applyFilters(QueryBuilder $qb, array $filters = [])
    {
        $this->filterLocation($qb, $filters);
        $this->filterMaterialType($qb, $filters);
        $this->filterSeatingType($qb, $filters);
        $this->filterModel($qb, $filters);
        $this->filterManufacturer($qb, $filters);
        $this->filterOpeningDate($qb, $filters);
    }

    private function filterLocation(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('country', $filters) && $filters['country'] !== '') {
            $qb
                ->join('p.country', 'co')
                ->andWhere('co.id = :country')
                ->setParameter('country', $filters['country']);
        } elseif (array_key_exists('continent', $filters) && $filters['continent'] !== '') {
            $qb
                ->join('p.country', 'co')
                ->join('co.continent', 'ct')
                ->andWhere('ct.id = :continent')
                ->setParameter('continent', $filters['continent']);
        }
    }

    private function filterMaterialType(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('materialType', $filters) && $filters['materialType'] !== '') {
            $qb
                ->join('c.materialType', 'mt')
                ->andWhere('mt.id = :materialType')
                ->setParameter('materialType', $filters['materialType']);
        }
    }

    private function filterSeatingType(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('seatingType', $filters) && $filters['seatingType'] !== '') {
            $qb
                ->join('c.seatingType', 'st')
                ->andWhere('st.id = :seatingType')
                ->setParameter('seatingType', $filters['seatingType']);
        }
    }

    private function filterModel(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('model', $filters) && $filters['model'] !== '') {
            $qb
                ->join('c.model', 'mo')
                ->andWhere('mo.id = :model')
                ->setParameter('model', $filters['model']);
        }
    }

    private function filterManufacturer(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('manufacturer', $filters) && $filters['manufacturer'] !== '') {
            $qb
                ->andWhere('m.id = :manufacturer')
                ->setParameter('manufacturer', $filters['manufacturer']);
        }
    }

    private function filterOpeningDate(QueryBuilder $qb, array $filters = [])
    {
        // Filter by average rating
        if (array_key_exists('openingDate', $filters) && $filters['openingDate'] !== '') {
            $qb
                ->andWhere('c.openingDate like :date')
                ->setParameter('date', sprintf('%%%s%%', $filters['openingDate']));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Ranking;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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
    public function findCoastersRanked(array $filters = [])
    {
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'p', 'm')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.park', 'p')
            ->innerJoin('c.status', 's')
            ->leftJoin('c.manufacturer', 'm')
            ->where('c.rank is not null');

        $qb->orderBy('c.rank', 'asc');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery();
    }

    private function applyFilters(QueryBuilder $qb, array $filters = []): void
    {
        $this->filterLocation($qb, $filters);
        $this->filterMaterialType($qb, $filters);
        $this->filterSeatingType($qb, $filters);
        $this->filterModel($qb, $filters);
        $this->filterManufacturer($qb, $filters);
        $this->filterOpeningDate($qb, $filters);
        $this->filterOpenedStatus($qb, $filters);
        $this->filterByNotRidden($qb, $filters);
    }

    private function filterLocation(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('country', $filters) && '' !== $filters['country']) {
            $qb
                ->join('p.country', 'co')
                ->andWhere('co.id = :country')
                ->setParameter('country', $filters['country']);
        } elseif (\array_key_exists('continent', $filters) && '' !== $filters['continent']) {
            $qb
                ->join('p.country', 'co')
                ->join('co.continent', 'ct')
                ->andWhere('ct.id = :continent')
                ->setParameter('continent', $filters['continent']);
        }
    }

    private function filterMaterialType(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('materialType', $filters) && '' !== $filters['materialType']) {
            $qb
                ->join('c.materialType', 'mt')
                ->andWhere('mt.id = :materialType')
                ->setParameter('materialType', $filters['materialType']);
        }
    }

    private function filterSeatingType(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('seatingType', $filters) && '' !== $filters['seatingType']) {
            $qb
                ->join('c.seatingType', 'st')
                ->andWhere('st.id = :seatingType')
                ->setParameter('seatingType', $filters['seatingType']);
        }
    }

    private function filterModel(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('model', $filters) && '' !== $filters['model']) {
            $qb
                ->join('c.model', 'mo')
                ->andWhere('mo.id = :model')
                ->setParameter('model', $filters['model']);
        }
    }

    private function filterManufacturer(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('manufacturer', $filters) && '' !== $filters['manufacturer']) {
            $qb
                ->andWhere('m.id = :manufacturer')
                ->setParameter('manufacturer', $filters['manufacturer']);
        }
    }

    private function filterOpeningDate(QueryBuilder $qb, array $filters = []): void
    {
        // Filter by average rating
        if (\array_key_exists('openingDate', $filters) && '' !== $filters['openingDate']) {
            $qb
                ->andWhere('c.openingDate like :date')
                ->setParameter('date', \sprintf('%%%s%%', $filters['openingDate']));
        }
    }

    /** Filter only operating coasters. */
    private function filterOpenedStatus(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('status', $filters)) {
            $qb
                ->andWhere('s.name = :operating')
                ->setParameter('operating', Status::OPERATING);
        }
    }

    /** Filter coasters user has not ridden. User based filter. */
    private function filterByNotRidden(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('notridden', $filters) && 'on' === $filters['notridden']
            && \array_key_exists('user', $filters) && !empty($filters['user'])) {
            $qb2 = $this
                ->getEntityManager()
                ->createQueryBuilder()
                ->select('c2.id')
                ->from(RiddenCoaster::class, 'rc2')
                ->innerJoin('rc2.coaster', 'c2', 'WITH', 'rc2.coaster = c2.id')
                ->where('rc2.user = :userid');

            $qb
                ->andWhere($qb->expr()->notIn('c.id', $qb2->getDQL()))
                ->setParameter('userid', $filters['user']);
        }
    }
}

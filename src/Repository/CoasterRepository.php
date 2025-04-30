<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class CoasterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Coaster::class);
    }

    public function suggestCoasterForTop(string $term, User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id', 'c.name as coaster', 'p.name as park', 'r.value as rating')
            ->from(Coaster::class, 'c')
            ->join('c.park', 'p')
            ->leftJoin('c.ratings', 'r', Expr\Join::WITH, 'r.user = :user')
            ->where('c.name LIKE :term')
            ->orWhere('p.name LIKE :term')
            ->orWhere('c.slug LIKE :term2')
            ->orWhere('p.slug LIKE :term2')
            ->setParameter('term', \sprintf('%%%s%%', $term))
            ->setParameter('term2', str_replace(' ', '-', \sprintf('%%%s%%', $term)))
            ->setParameter('user', $user)
            ->setMaxResults(15)
            ->getQuery()
            ->getResult();
    }

    /** @return array */
    public function findAllForSearch()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('CONCAT(c.name, \' - \', p.name) AS name')
            ->addSelect('c.slug')
            ->addSelect('c.formerNames')
            ->from(Coaster::class, 'c')
            ->orderBy('c.score', 'DESC')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->getQuery()
            ->getResult();
    }

    public function getFilteredMarkers(array $filters): array
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('p.name as name')
            ->addSelect('p.latitude as latitude')
            ->addSelect('p.longitude as longitude')
            ->addSelect('count(1) as nb')
            ->addSelect('p.id as id')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->leftJoin('c.manufacturer', 'm', 'WITH', 'c.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null')
            ->groupBy('c.park');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getArrayResult();
    }

    /** @return array */
    public function getCoastersForMap(Park $park, array $filters)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb
            ->select(['c', 's', 'p'])
            ->from(Coaster::class, 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->leftJoin('c.manufacturer', 'm', 'WITH', 'c.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.id = :parkId')
            ->setParameter('parkId', $park->getId());

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * Return coasters for nearby page.
     *
     * @return Query
     */
    public function getNearbyCoasters(array $filters)
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c AS item')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->leftJoin('c.manufacturer', 'm', 'WITH', 'c.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery();
    }

    public function getDistinctOpeningYears()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('year', 'year');

        return $this->getEntityManager()
            ->createNativeQuery('SELECT DISTINCT YEAR(c.openingDate) as year from coaster c ORDER by year DESC', $rsm)
            ->getScalarResult();
    }

    /** Find a newly ranked coaster to add in neach month notification */
    public function getNewlyRankedHighlightedCoaster($maxRank = 300)
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c')
            ->from(Coaster::class, 'c')
            ->andWhere('c.previousRank is null')
            ->andWhere('c.rank < :maxRank')
            ->setParameter('maxRank', $maxRank)
            ->orderBy('c.rank', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Get all coasters in a park, nicely ordered */
    public function findAllCoastersInPark(Park $park)
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c')
            ->from(Coaster::class, 'c')
            ->innerJoin('c.status', 's')
            ->andWhere('c.park = :park')
            ->setParameter('park', $park)
            ->orderBy('s.order', 'ASC')
            ->addOrderBy('c.score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function applyFilters(QueryBuilder $qb, array $filters = []): void
    {
        // Filter by manufacturer
        $this->filterManufacturer($qb, $filters);
        // Filter by opened status
        $this->filterOpenedStatus($qb, $filters);
        // Filter by score
        $this->filterScore($qb, $filters);
        // Filter by opening date
        $this->filterOpeningDate($qb, $filters);
        // Filter by not ridden. User based filter.
        $this->filterByNotRidden($qb, $filters);
        // Filter by ridden. User based filter.
        $this->filterByRidden($qb, $filters);
        // Filter kiddie.
        $this->filterKiddie($qb, $filters);
        // Filter name.
        $this->filterName($qb, $filters);
        // Order by
        $this->orderBy($qb, $filters);
    }

    private function orderBy(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('latitude', $filters) && '' !== $filters['latitude']) {
            $qb->addSelect(
                'POWER(POWER(111.2 * (p.latitude - :latitude), 2) + POWER(111.2 * (:longitude - p.longitude) * COS(p.latitude / 57.3), 2), 0.5) AS distance'
            )
                ->orderBy('distance', 'asc')
                ->setParameter('latitude', $filters['latitude'])
                ->setParameter('longitude', $filters['longitude']);
        } else {
            $qb->orderBy('c.updatedAt', 'desc');
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

    /** Filter only operating coasters. */
    private function filterOpenedStatus(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('status', $filters)) {
            $qb
                ->andWhere('s.name = :operating')
                ->setParameter('operating', Status::OPERATING);
        }
    }

    private function filterScore(QueryBuilder $qb, array $filters = []): void
    {
        // Filter by average rating
        if (\array_key_exists('score', $filters) && '' !== $filters['score']) {
            $qb
                ->andWhere('c.score >= :rating')
                ->setParameter('rating', $filters['score']);
        }
    }

    private function filterByRidden(QueryBuilder $qb, array $filters = []): void
    {
        // Filter by not ridden. User based filter.
        if (\array_key_exists('ridden', $filters) && 'on' === $filters['ridden']
            && \array_key_exists('user', $filters) && !empty($filters['user'])) {
            $qb2 = $this
                ->getEntityManager()
                ->createQueryBuilder()
                ->select('c1.id')
                ->from(RiddenCoaster::class, 'rc1')
                ->innerJoin('rc1.coaster', 'c1', 'WITH', 'rc1.coaster = c1.id')
                ->where('rc1.user = :userid');

            $qb
                ->andWhere($qb->expr()->in('c.id', $qb2->getDQL()))
                ->setParameter('userid', $filters['user']);
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

    private function filterOpeningDate(QueryBuilder $qb, array $filters = []): void
    {
        // Filter by average rating
        if (\array_key_exists('openingDate', $filters) && '' !== $filters['openingDate']) {
            $qb
                ->andWhere('c.openingDate like :date')
                ->setParameter('date', \sprintf('%%%s%%', $filters['openingDate']));
        }
    }

    private function filterKiddie(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('kiddie', $filters) && '' !== $filters['kiddie']) {
            $qb->andWhere('c.kiddie = 0');
        }
    }

    private function filterName(QueryBuilder $qb, array $filters = []): void
    {
        if (\array_key_exists('name', $filters) && '' !== $filters['name']) {
            $qb
                ->andWhere('c.name like :name')
                ->setParameter('name', \sprintf('%%%s%%', $filters['name']));
        }
    }
}

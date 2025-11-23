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

    /**
     * Find coasters for a specific park (used in map popups).
     * Returns array of coaster entities.
     * Expects filters to already be validated and authorized.
     *
     * @param Park                 $park    The park to get coasters for
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return array<Coaster> Array of coaster entities
     */
    public function findForPark(Park $park, array $filters = []): array
    {
        $qb = $this->createBaseQuery()
            ->select('c', 's', 'p')
            ->where('p.id = :parkId')
            ->setParameter('parkId', $park->getId());

        $this->applyFilters($qb, $filters, 'map');

        return $qb->getQuery()->getResult();
    }

    /** Optimized search method for API with limited results and better performance. */
    public function findBySearchQuery(string $query, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id', 'c.name', 'c.slug', 'p.name as parkName', 'co.name as countryName')
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'co')
            ->where('c.name LIKE :query OR c.slug LIKE :slugQuery')
            ->setParameter('query', '%'.$query.'%')
            ->setParameter('slugQuery', '%'.str_replace(' ', '-', $query).'%')
            ->orderBy('c.score', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getDistinctOpeningYears()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('year', 'year');

        return $this->getEntityManager()
            ->createNativeQuery('SELECT DISTINCT YEAR(c.openingDate) as year from coaster c WHERE YEAR(c.openingDate) > 1800 ORDER by year DESC', $rsm)
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

    /**
     * Find coasters for search page.
     * Returns Query object for pagination.
     * Expects filters to already be validated and authorized.
     *
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return Query Query object for pagination
     */
    public function findForSearch(array $filters = []): Query
    {
        $qb = $this->createBaseQuery()->select('c');
        $this->applyFilters($qb, $filters, 'search');
        $qb->orderBy('c.updatedAt', 'DESC');

        return $qb->getQuery();
    }

    /**
     * Find coasters for ranking page.
     * Returns Query object for pagination.
     * Expects filters to already be validated and authorized.
     *
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return Query Query object for pagination
     */
    public function findForRanking(array $filters = []): Query
    {
        $qb = $this->createBaseQuery()
            ->select('c', 'p', 'm')
            ->andWhere('c.rank IS NOT NULL');

        $this->applyFilters($qb, $filters, 'ranking');
        $qb->orderBy('c.rank', 'ASC');

        return $qb->getQuery();
    }

    /**
     * Find map markers for all parks with coasters.
     * Returns array of marker data (not Query object).
     * Expects filters to already be validated and authorized.
     *
     * @param array<string, mixed> $filters Validated and authorized filter array
     *
     * @return array<array{name: string, latitude: float, longitude: float, nb: int, id: int}> Array of marker data
     */
    public function findForMap(array $filters = []): array
    {
        $qb = $this->createBaseQuery()
            ->select('p.name as name, p.latitude as latitude, p.longitude as longitude, count(1) as nb, p.id as id')
            ->andWhere('p.latitude IS NOT NULL')
            ->andWhere('p.longitude IS NOT NULL')
            ->groupBy('c.park');

        $this->applyFilters($qb, $filters, 'map');

        return $qb->getQuery()->getArrayResult();
    }

    private function createBaseQuery()
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'country')
            ->leftJoin('country.continent', 'continent')
            ->leftJoin('c.manufacturer', 'm')
            ->leftJoin('c.materialType', 'mt')
            ->leftJoin('c.seatingType', 'st')
            ->leftJoin('c.model', 'model')
            ->leftJoin('c.status', 's');
    }

    /**
     * Orchestrates all filter groups and applies them to the query builder.
     * Expects filters to already be validated and sanitized.
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Validated and sanitized filter array
     * @param string               $context Context where filters are applied (ranking, search, map)
     */
    private function applyFilters(QueryBuilder $qb, array $filters, string $context = 'search'): void
    {
        // Apply filters in logical groups
        $this->applyLocationFilters($qb, $filters);
        $this->applyCharacteristicFilters($qb, $filters);
        $this->applyStatusFilters($qb, $filters);
        $this->applyUserFilters($qb, $filters);

        // Apply context-specific filters
        if ('ranking' === $context) {
            $this->applyRankingFilters($qb, $filters);
        }
    }

    /**
     * Applies location-based filters (continent, country).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyLocationFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['continent'])) {
            $qb->andWhere('continent.id = :continent')
               ->setParameter('continent', $filters['continent']);
        }

        if (!empty($filters['country'])) {
            $qb->andWhere('country.id = :country')
               ->setParameter('country', $filters['country']);
        }
    }

    /**
     * Applies coaster characteristic filters (manufacturer, material, seating, model, opening date, name, score).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyCharacteristicFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['manufacturer'])) {
            $qb->andWhere('m.id = :manufacturer')
               ->setParameter('manufacturer', $filters['manufacturer']);
        }

        if (!empty($filters['materialType'])) {
            $qb->andWhere('mt.id = :materialType')
               ->setParameter('materialType', $filters['materialType']);
        }

        if (!empty($filters['seatingType'])) {
            $qb->andWhere('st.id = :seatingType')
               ->setParameter('seatingType', $filters['seatingType']);
        }

        if (!empty($filters['model'])) {
            $qb->andWhere('model.id = :model')
               ->setParameter('model', $filters['model']);
        }

        if (!empty($filters['openingDate'])) {
            $year = $filters['openingDate'];
            $qb->andWhere('c.openingDate BETWEEN :yearStart AND :yearEnd')
               ->setParameter('yearStart', $year.'-01-01')
               ->setParameter('yearEnd', $year.'-12-31');
        }

        if (!empty($filters['name'])) {
            $qb->andWhere('c.name LIKE :name')
               ->setParameter('name', '%'.$filters['name'].'%');
        }

        if (!empty($filters['score'])) {
            $qb->andWhere('c.score >= :score')
               ->setParameter('score', $filters['score']);
        }
    }

    /**
     * Applies status-based filters (operating status, kiddie).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyStatusFilters(QueryBuilder $qb, array $filters): void
    {
        if (isset($filters['status']) && 'on' === $filters['status']) {
            $qb->andWhere('s.name = :operating')
               ->setParameter('operating', Status::OPERATING);
        }

        if (isset($filters['kiddie'])) {
            $qb->andWhere('c.kiddie = :kiddie')
               ->setParameter('kiddie', 'on' !== $filters['kiddie']);
        }
    }

    /**
     * Applies user-specific filters (ridden, not ridden).
     * Expects permissions to already be checked.
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Validated and authorized filter array
     */
    private function applyUserFilters(QueryBuilder $qb, array $filters): void
    {
        if (empty($filters['user'])) {
            return;
        }

        $userId = $filters['user'];

        if (isset($filters['ridden']) && 'on' === $filters['ridden']) {
            $subQuery = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('rc_c.id')
                ->from(RiddenCoaster::class, 'rc')
                ->innerJoin('rc.coaster', 'rc_c')
                ->where('rc.user = :userId');

            $qb->andWhere($qb->expr()->in('c.id', $subQuery->getDQL()))
               ->setParameter('userId', $userId);
        }

        if (isset($filters['notridden']) && 'on' === $filters['notridden']) {
            $subQuery = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('rc_c.id')
                ->from(RiddenCoaster::class, 'rc')
                ->innerJoin('rc.coaster', 'rc_c')
                ->where('rc.user = :userId');

            $qb->andWhere($qb->expr()->notIn('c.id', $subQuery->getDQL()))
               ->setParameter('userId', $userId);
        }
    }

    /**
     * Applies ranking-specific filters (new coasters in ranking).
     *
     * @param QueryBuilder         $qb      The query builder to apply filters to
     * @param array<string, mixed> $filters Sanitized filter array
     */
    private function applyRankingFilters(QueryBuilder $qb, array $filters): void
    {
        // 'new' filter: coasters new to ranking this month
        if (isset($filters['new']) && 'on' === $filters['new']) {
            $qb->andWhere('c.previousRank IS NULL')
               ->andWhere('c.rank IS NOT NULL');
        }
    }
}

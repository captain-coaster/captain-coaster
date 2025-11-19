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

    public function getFilteredMarkers(array $filters): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('p.name as name')
            ->addSelect('p.latitude as latitude')
            ->addSelect('p.longitude as longitude')
            ->addSelect('count(1) as nb')
            ->addSelect('p.id as id')
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'country')
            ->leftJoin('country.continent', 'continent')
            ->leftJoin('c.manufacturer', 'm')
            ->leftJoin('c.materialType', 'mt')
            ->leftJoin('c.seatingType', 'st')
            ->leftJoin('c.model', 'model')
            ->leftJoin('c.status', 's')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null')
            ->groupBy('c.park');

        if (!empty($filters)) {
            $this->applyAllFilters($qb, $filters);
        }

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
            ->leftJoin('p.country', 'country')
            ->leftJoin('country.continent', 'continent')
            ->leftJoin('c.manufacturer', 'm', 'WITH', 'c.manufacturer = m.id')
            ->leftJoin('c.materialType', 'mt')
            ->leftJoin('c.seatingType', 'st')
            ->leftJoin('c.model', 'model')
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
        $qb = $this->buildFilteredQuery($filters)
            ->select('c AS item')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null');

        $this->orderBy($qb, $filters);
        return $qb->getQuery();
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

    /**
     * Unified method to get filtered coasters in different formats
     */
    public function getFilteredCoasters(array $filters, string $format = 'entities')
    {
        // Add format context to filters for format-specific behavior
        $filters['_format'] = $format;
        $qb = $this->buildFilteredQuery($filters);
        
        switch ($format) {
            case 'ranking':
                $qb->select('c', 'p', 'm')
                   ->andWhere('c.rank IS NOT NULL')
                   ->orderBy('c.rank', 'ASC');
                return $qb->getQuery();
                
            case 'nearby':
                $qb->select('c AS item')
                   ->andWhere('p.latitude IS NOT NULL')
                   ->andWhere('p.longitude IS NOT NULL');
                $this->orderBy($qb, $filters);
                return $qb->getQuery();
                
            case 'markers':
                $qb->select('p.name as name')
                   ->addSelect('p.latitude as latitude')
                   ->addSelect('p.longitude as longitude')
                   ->addSelect('count(1) as nb')
                   ->addSelect('p.id as id')
                   ->andWhere('p.latitude IS NOT NULL')
                   ->andWhere('p.longitude IS NOT NULL')
                   ->groupBy('c.park');
                return $qb->getQuery()->getArrayResult();
                
            default:
                return $qb->getQuery();
        }
    }

    /**
     * Build base query with all necessary joins for filtering
     */
    private function buildFilteredQuery(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.park', 'p')
            ->leftJoin('p.country', 'country')
            ->leftJoin('country.continent', 'continent')
            ->leftJoin('c.manufacturer', 'm')
            ->leftJoin('c.materialType', 'mt')
            ->leftJoin('c.seatingType', 'st')
            ->leftJoin('c.model', 'model')
            ->leftJoin('c.status', 's');

        if (!empty($filters)) {
            $this->applyAllFilters($qb, $filters);
        }
        return $qb;
    }

    /**
     * Apply all available filters to query
     */
    private function applyAllFilters(QueryBuilder $qb, array $filters = []): void
    {
        // Location filters
        $this->filterContinent($qb, $filters);
        $this->filterCountry($qb, $filters);
        
        // Coaster attribute filters
        $this->filterManufacturer($qb, $filters);
        $this->filterMaterialType($qb, $filters);
        $this->filterSeatingType($qb, $filters);
        $this->filterModel($qb, $filters);
        
        // Status and date filters
        $this->filterOpenedStatus($qb, $filters);
        $this->filterOpeningDate($qb, $filters);
        $this->filterScore($qb, $filters);
        
        // User-based filters
        $this->filterByNotRidden($qb, $filters);
        $this->filterByRidden($qb, $filters);
        
        // Special filters
        $this->filterKiddie($qb, $filters);
        $this->filterNew($qb, $filters);
        $this->filterName($qb, $filters);
    }

    private function applyFilters(QueryBuilder $qb, array $filters = []): void
    {
        $this->applyAllFilters($qb, $filters);
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

    private function filterContinent(QueryBuilder $qb, array $filters = []): void
    {
        if (!empty($filters['continent'])) {
            $qb->andWhere('continent.id = :continent')
               ->setParameter('continent', $filters['continent']);
        }
    }

    private function filterCountry(QueryBuilder $qb, array $filters = []): void
    {
        if (!empty($filters['country'])) {
            $qb->andWhere('country.id = :country')
               ->setParameter('country', $filters['country']);
        }
    }

    private function filterMaterialType(QueryBuilder $qb, array $filters = []): void
    {
        if (!empty($filters['materialType'])) {
            $qb->andWhere('mt.id = :materialType')
               ->setParameter('materialType', $filters['materialType']);
        }
    }

    private function filterSeatingType(QueryBuilder $qb, array $filters = []): void
    {
        if (!empty($filters['seatingType'])) {
            $qb->andWhere('st.id = :seatingType')
               ->setParameter('seatingType', $filters['seatingType']);
        }
    }

    private function filterModel(QueryBuilder $qb, array $filters = []): void
    {
        if (!empty($filters['model'])) {
            $qb->andWhere('model.id = :model')
               ->setParameter('model', $filters['model']);
        }
    }

    private function filterNew(QueryBuilder $qb, array $filters = []): void
    {
        if (isset($filters['new']) && $filters['new'] === 'on') {
            // For ranking, 'new' means new in ranking (previousRank is null)
            // For other pages, 'new' means recently opened coasters
            if (isset($filters['_format']) && $filters['_format'] === 'ranking') {
                $qb->andWhere('c.previousRank IS NULL');
            } else {
                $qb->andWhere('c.openingDate >= :twoYearsAgo')
                   ->setParameter('twoYearsAgo', new \DateTime('-2 years'));
            }
        }
    }

    private function filterKiddie(QueryBuilder $qb, array $filters = []): void
    {
        if (isset($filters['kiddie']) && $filters['kiddie'] === 'on') {
            $qb->andWhere('c.kiddie = true');
        } elseif (\array_key_exists('kiddie', $filters) && '' !== $filters['kiddie']) {
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

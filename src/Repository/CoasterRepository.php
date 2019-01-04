<?php

namespace App\Repository;

use App\Entity\Park;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;

/**
 * CoasterRepository
 */
class CoasterRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @param string $term
     * @param User $user
     * @return array
     */
    public function suggestCoasterForListe(string $term, User $user)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id', 'c.name as coaster', 'p.name as park', 'r.value as rating')
            ->from('App:Coaster', 'c')
            ->join('c.park', 'p')
            ->leftJoin('c.ratings', 'r', Expr\Join::WITH, 'r.user = :user')
            ->where('c.name LIKE :term')
            ->orWhere('p.name LIKE :term')
            ->setParameter('term', sprintf('%%%s%%', $term))
            ->setParameter('user', $user)
            ->setMaxResults(15)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array
     */
    public function findAllForSearch()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('CONCAT(c.name, \' - \', p.name) AS name')
            ->addSelect('c.slug')
            ->from('App:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array $filters
     * @return array
     */
    public function getFilteredMarkers(array $filters)
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('p.name as name')
            ->addSelect('p.latitude as latitude')
            ->addSelect('p.longitude as longitude')
            ->addSelect('count(1) as nb')
            ->addSelect('p.id as id')
            ->from('App:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->leftJoin('c.manufacturer', 'm', 'WITH', 'c.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null')
            ->groupBy('c.park');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Park $park
     * @param array $filters
     * @return array
     */
    public function getCoastersForMap(Park $park, array $filters)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb
            ->select(['c', 's', 'p'])
            ->from('App:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->leftJoin('c.manufacturer', 'm', 'WITH', 'c.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.id = :parkId')
            ->setParameter('parkId', $park->getId());

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * Return coasters for nearby page
     *
     * @param array $filters
     * @return \Doctrine\ORM\Query
     */
    public function getSearchCoasters(array $filters)
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c AS item')
            ->from('App:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->leftJoin('c.manufacturer', 'm', 'WITH', 'c.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery();
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters = [])
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

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function orderBy(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('latitude', $filters) && $filters['latitude'] !== '') {
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

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterManufacturer(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('manufacturer', $filters) && $filters['manufacturer'] !== '') {
            $qb
                ->andWhere('m.id = :manufacturer')
                ->setParameter('manufacturer', $filters['manufacturer']);
        }
    }

    /**
     * Filter only operating coasters
     *
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterOpenedStatus(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('status', $filters)) {
            $qb
                ->andWhere('s.name = :operating')
                ->setParameter('operating', Status::OPERATING);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterScore(QueryBuilder $qb, array $filters = [])
    {
        // Filter by average rating
        if (array_key_exists('score', $filters) && $filters['score'] !== '') {
            $qb
                ->andWhere('c.score >= :rating')
                ->setParameter('rating', $filters['score']);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterByRidden(QueryBuilder $qb, array $filters = [])
    {
        // Filter by not ridden. User based filter.
        if (array_key_exists('ridden', $filters) && $filters['ridden'] === 'on'
            && array_key_exists('user', $filters) && !empty($filters['user'])) {
            $qb2 = $this
                ->getEntityManager()
                ->createQueryBuilder()
                ->select('c1.id')
                ->from('App:RiddenCoaster', 'rc1')
                ->innerJoin('rc1.coaster', 'c1', 'WITH', 'rc1.coaster = c1.id')
                ->where('rc1.user = :userid');

            $qb
                ->andWhere($qb->expr()->in('c.id', $qb2->getDQL()))
                ->setParameter('userid', $filters['user']);
        }
    }

    /**
     * Filter coasters user has not ridden. User based filter.
     *
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterByNotRidden(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('notridden', $filters) && $filters['notridden'] === 'on'
            && array_key_exists('user', $filters) && !empty($filters['user'])) {
            $qb2 = $this
                ->getEntityManager()
                ->createQueryBuilder()
                ->select('c2.id')
                ->from('App:RiddenCoaster', 'rc2')
                ->innerJoin('rc2.coaster', 'c2', 'WITH', 'rc2.coaster = c2.id')
                ->where('rc2.user = :userid');

            $qb
                ->andWhere($qb->expr()->notIn('c.id', $qb2->getDQL()))
                ->setParameter('userid', $filters['user']);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterOpeningDate(QueryBuilder $qb, array $filters = [])
    {
        // Filter by average rating
        if (array_key_exists('openingDate', $filters) && $filters['openingDate'] !== '') {
            $qb
                ->andWhere('c.openingDate like :date')
                ->setParameter('date', sprintf('%%%s%%', $filters['openingDate']));
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterKiddie(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('kiddie', $filters) && $filters['kiddie'] !== '') {
            $qb->andWhere('c.kiddie = 0');
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterName(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('name', $filters) && $filters['name'] !== '') {
            $qb
                ->andWhere('c.name like :name')
                ->setParameter('name', sprintf('%%%s%%', $filters['name']));
        }
    }

    /**
     * @return mixed
     */
    public function getDistinctOpeningYears()
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('year', 'year');

        return $this->getEntityManager()
            ->createNativeQuery('SELECT DISTINCT YEAR(c.openingDate) as year from coaster c ORDER by year DESC', $rsm)
            ->getScalarResult();
    }
}

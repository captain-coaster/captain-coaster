<?php

namespace BddBundle\Repository;

use BddBundle\Entity\Park;
use BddBundle\Entity\User;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;

/**
 * CoasterRepository
 */
class CoasterRepository extends \Doctrine\ORM\EntityRepository
{

    /**
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countAll()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(1)')
            ->from('BddBundle:Coaster', 'c')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param string $term
     * @return array
     */
    public function searchByName(string $term)
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id', 'c.name as coaster', 'p.name as park')
            ->from('BddBundle:Coaster', 'c')
            ->join('c.park', 'p')
            ->where('c.name LIKE :term')
            ->setParameter('term', sprintf('%%%s%%', $term))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array
     */
    public function findAllNameAndSlug()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('CONCAT(c.name, \' - \', p.name) AS name')
            ->addSelect('c.slug')
            ->from('BddBundle:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return \Doctrine\ORM\Query
     */
    public function findByRanking()
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c', 'p', 'bc', 'm')
            ->from('BddBundle:Coaster', 'c')
            ->innerJoin('c.park', 'p')
            ->innerJoin('c.builtCoaster', 'bc')
            ->innerJoin('bc.manufacturer', 'm')
            ->where('c.rank is not null')
            ->orderBy('c.rank', 'asc')
            ->getQuery();
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
            ->from('BddBundle:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->innerJoin('c.builtCoaster', 'bc', 'WITH', 'c.builtCoaster = bc.id')
            ->innerJoin('bc.manufacturer', 'm', 'WITH', 'bc.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null')
            ->groupBy('c.park');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Park  $park
     * @param array $filters
     * @return array
     */
    public function getCoastersForMap(Park $park, array $filters)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb
            ->select(['c', 's', 'p'])
            ->from('BddBundle:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->innerJoin('c.builtCoaster', 'bc', 'WITH', 'c.builtCoaster = bc.id')
            ->innerJoin('bc.manufacturer', 'm', 'WITH', 'bc.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.id = :parkId')
            ->setParameter('parkId', $park->getId());

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $filters
     * @param User|null    $user
     */
    private function applyFilters(QueryBuilder $qb, array $filters = [], $user = null)
    {
        // Filter by manufacturer
        $this->filterManufacturer($qb, $filters);
        // Filter by opened status
        $this->filterOpenedStatus($qb, $filters);
        // Filter by average rating
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
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $filters
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
     * @param QueryBuilder $qb
     * @param array        $filters
     */
    private function filterOpenedStatus(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('status', $filters)) {
            $qb->andWhere('s.id = 1');
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $filters
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
     * @param array        $filters
     */
    private function filterByNotRidden(QueryBuilder $qb, array $filters = [])
    {
        // Filter by not ridden. User based filter.
        if (array_key_exists('notridden', $filters) && array_key_exists('user', $filters)) {
            $qb2 = $this
                ->getEntityManager()
                ->createQueryBuilder()
                ->select('c2.id')
                ->from('BddBundle:RiddenCoaster', 'rc')
                ->innerJoin('rc.coaster', 'c2', 'WITH', 'rc.coaster = c2.id')
                ->where('rc.user = :userid');

            $qb
                ->andWhere($qb->expr()->notIn('c.id', $qb2->getDQL()))
                ->setParameter('userid', $filters['user']);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $filters
     */
    private function filterByRidden(QueryBuilder $qb, array $filters = [])
    {
        // Filter by not ridden. User based filter.
        if (array_key_exists('ridden', $filters) && array_key_exists('user', $filters)) {
            $qb2 = $this
                ->getEntityManager()
                ->createQueryBuilder()
                ->select('c2.id')
                ->from('BddBundle:RiddenCoaster', 'rc')
                ->innerJoin('rc.coaster', 'c2', 'WITH', 'rc.coaster = c2.id')
                ->where('rc.user = :userid');

            $qb
                ->andWhere($qb->expr()->in('c.id', $qb2->getDQL()))
                ->setParameter('userid', $filters['user']);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $filters
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
     * @param array        $filters
     */
    private function filterKiddie(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('kiddie', $filters) && $filters['kiddie'] !== '') {
            $qb
                ->andWhere('bc.isKiddie = 0');
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array        $filters
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
     * @param array $filters
     * @return array
     */
    public function getFilteredCoasters(array $filters)
    {
        $qb = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from('BddBundle:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->innerJoin('c.builtCoaster', 'bc', 'WITH', 'c.builtCoaster = bc.id')
            ->innerJoin('bc.manufacturer', 'm', 'WITH', 'bc.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id');

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
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

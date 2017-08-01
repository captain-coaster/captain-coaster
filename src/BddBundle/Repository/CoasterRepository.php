<?php

namespace BddBundle\Repository;

use BddBundle\Entity\Park;
use BddBundle\Entity\User;
use Doctrine\ORM\QueryBuilder;

/**
 * CoasterRepository
 */
class CoasterRepository extends \Doctrine\ORM\EntityRepository
{
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
            ->where('c.averageRating is not null')
            ->orderBy('c.averageRating', 'desc')
            ->getQuery();
    }

    /**
     * @param array $filters
     * @param $user User|null
     * @return array
     */
    public function getFilteredMarkers(array $filters, $user)
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

        $this->applyFilters($qb, $filters, $user);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Park $park
     * @param array $filters
     * @param $user User|null
     * @return array
     */
    public function getCoastersForMap(Park $park, array $filters, $user)
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

        $this->applyFilters($qb, $filters, $user);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     * @param User|null $user
     */
    private function applyFilters(QueryBuilder $qb, array $filters = [], $user = null)
    {
        // Filter by manufacturer
        $this->filterManufacturer($qb, $filters);
        // Filter by opened status
        $this->filterOpenedStatus($qb, $filters);
        // Filter by average rating
        $this->filterAverageRating($qb, $filters);
        // Filter by opening date
        $this->filterOpeningDate($qb, $filters);
        // Filter by not ridden. User based filter.
        $this->filterByNotRidden($qb, $filters, $user);
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
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterOpenedStatus(QueryBuilder $qb, array $filters = [])
    {
        if (array_key_exists('status', $filters)) {
            $qb->andWhere('s.id = 1');
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     */
    private function filterAverageRating(QueryBuilder $qb, array $filters = [])
    {
        // Filter by average rating
        if (array_key_exists('averageRating', $filters) && $filters['averageRating'] !== '') {
            $qb
                ->andWhere('c.averageRating >= :rating')
                ->setParameter('rating', $filters['averageRating']);
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $filters
     * @param User|null $user
     */
    private function filterByNotRidden(QueryBuilder $qb, array $filters = [], $user = null)
    {
        // Filter by not ridden. User based filter.
        if (array_key_exists('notridden', $filters) && $user instanceof User) {
            $qb2 = $this
                ->getEntityManager()
                ->createQueryBuilder()
                ->select('c2.id')
                ->from('BddBundle:RiddenCoaster', 'rc')
                ->innerJoin('rc.coaster', 'c2', 'WITH', 'rc.coaster = c2.id')
                ->where('rc.user = :userid');

            $qb
                ->andWhere($qb->expr()->notIn('c.id', $qb2->getDQL()))
                ->setParameter('userid', $user->getId());
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
}

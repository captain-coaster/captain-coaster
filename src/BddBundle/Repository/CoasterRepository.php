<?php

namespace BddBundle\Repository;

use BddBundle\Entity\User;

/**
 * CoasterRepository
 */
class CoasterRepository extends \Doctrine\ORM\EntityRepository
{
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
     * @param array $filters
     * @param $user User|null
     * @return array
     */
    public function getFilteredMarkers(array $filters, $user)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb
            ->select('p.name as name')
            ->addSelect('p.latitude as latitude')
            ->addSelect('p.longitude as longitude')
            ->addSelect('count(1) as nb')
            ->from('BddBundle:Coaster', 'c')
            ->innerJoin('c.park', 'p', 'WITH', 'c.park = p.id')
            ->innerJoin('c.builtCoaster', 'bc', 'WITH', 'c.builtCoaster = bc.id')
            ->innerJoin('bc.manufacturer', 'm', 'WITH', 'bc.manufacturer = m.id')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->where('p.latitude is not null')
            ->andWhere('p.longitude is not null')
            ->groupBy('c.park');

        // Filter by manufacturer
        if (array_key_exists('manufacturer', $filters) && $filters['manufacturer'] !== '') {
            $qb
                ->andWhere('m.id = :manufacturer')
                ->setParameter('manufacturer', $filters['manufacturer']);
        }

        // Filter by opened status
        if (array_key_exists('status', $filters)) {
            $qb->andWhere('s.id = 1');
        }

        // Filter by not ridden. User based filter.
        if (array_key_exists('notridden', $filters) && $user instanceof User) {
            $qb2 = $this->getEntityManager()->createQueryBuilder();
            $qb2
                ->select('c2.id')
                ->from('BddBundle:RiddenCoaster', 'rc')
                ->innerJoin('rc.coaster', 'c2', 'WITH', 'rc.coaster = c2.id')
                ->where('rc.user = :userid')
                ->getQuery();

            $qb
                ->andWhere($qb->expr()->notIn('c.id', $qb2->getDQL()))
                ->setParameter('userid', $user->getId());
        }

        return $qb->getQuery()->getResult();
    }
}

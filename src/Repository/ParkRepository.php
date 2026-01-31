<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Park>
 */
class ParkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Park::class);
    }

    /** @throws NonUniqueResultException */
    public function countForUser(User $user): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(DISTINCT(p.id))')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, array<string, mixed>> */
    public function getClosestParks(Park $park, int $minScore, int $maxDistance): array
    {
        $parkLatitude = $park->getLatitude();
        $parkLongitude = $park->getLongitude();

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('distinct p.name as name, ROUND(( 6371 * acos( cos( radians(:parkLatitude) )
              * cos( radians( p.latitude ) )
              * cos( radians( p.longitude ) - radians(:parkLongitude) )
              + sin( radians(:parkLatitude) )
              * sin( radians( p.latitude ) ) ) ) ) AS distance, p.slug as slug, p.id as id')
            ->from(Park::class, 'p')
            ->join('p.coasters', 'c')
            ->where('p.latitude between :parkLatitudeMin and :parkLatitudeMax')
            ->andwhere('p.longitude between :parkLongitudeMin and :parkLongitudeMax')
            ->andwhere('c.score > :minScore')
            ->innerJoin('c.status', 's', 'WITH', 'c.status = s.id')
            ->andWhere('s.name = :operating')
            ->setParameter('operating', Status::OPERATING)
            ->andwhere('p.id != :parkId')
            ->having('distance < :maxDistance')
            ->orderBy('distance')
            ->setParameter('parkLatitude', $parkLatitude)
            ->setParameter('parkLongitude', $parkLongitude)
            ->setParameter('parkLatitudeMin', $parkLatitude - 3)
            ->setParameter('parkLatitudeMax', $parkLatitude + 3)
            ->setParameter('parkLongitudeMin', $parkLongitude - 3)
            ->setParameter('parkLongitudeMax', $parkLongitude + 3)
            ->setParameter('minScore', $minScore)
            ->setParameter('parkId', $park->getId())
            ->setParameter('maxDistance', $maxDistance)
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Optimized search method for API with limited results and better performance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findBySearchQuery(string $query, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->select(
                'p.id',
                'p.name',
                'p.slug',
                'co.name as countryName',
                'COUNT(c.id) as coasterCount'
            )
            ->leftJoin('p.country', 'co')
            ->leftJoin('p.coasters', 'c', 'WITH', 'c.enabled = true')
            ->where('p.name LIKE :query OR p.slug LIKE :slugQuery')
            ->setParameter('query', '%'.$query.'%')
            ->setParameter('slugQuery', '%'.str_replace(' ', '-', $query).'%')
            ->groupBy('p.id')
            ->orderBy('coasterCount', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->enableResultCache(300) // Cache for 5 minutes
            ->getArrayResult();
    }
}

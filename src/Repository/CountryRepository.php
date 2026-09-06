<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Country;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Country>
 */
class CountryRepository extends ServiceEntityRepository
{
    use FilterableRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    public function countForUser(User $user): int
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('count(DISTINCT(co.id))')
            ->from(RiddenCoaster::class, 'r')
            ->join('r.coaster', 'c')
            ->join('c.park', 'p')
            ->join('p.country', 'co')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery();

        // Feeds StatService::getUserStats() (every profile page view) and
        // BannerService (S3 banner generation) -- 300s matches the other
        // per-user stats it's cached alongside.
        $query->enableResultCache(300);

        return (int) $query->getSingleScalarResult();
    }
}

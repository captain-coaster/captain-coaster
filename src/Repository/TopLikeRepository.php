<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Top;
use App\Entity\TopLike;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TopLike>
 */
class TopLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TopLike::class);
    }

    public function findOneByUserAndTop(User $user, Top $top): ?TopLike
    {
        return $this->findOneBy(['user' => $user, 'top' => $top]);
    }

    /** @return list<int> Top IDs liked by the given user. */
    public function findLikedTopIds(User $user): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.top) AS topId')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(static fn (array $row) => (int) $row['topId'], $rows));
    }
}

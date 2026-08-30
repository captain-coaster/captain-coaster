<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return array<int, Notification> */
    public function findUnreadForUser(User $user, int $limit): array
    {
        return $this
            ->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Deletes read notifications created before $before. Returns the number of rows removed. */
    public function deleteReadOlderThan(\DateTimeInterface $before): int
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->delete(Notification::class, 'n')
            ->where('n.isRead = true')
            ->andWhere('n.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    public function countReadOlderThan(\DateTimeInterface $before): int
    {
        return (int) $this
            ->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.isRead = true')
            ->andWhere('n.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markTypeAsRead(string $type): int
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.isRead', true)
            ->where('n.type LIKE :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->execute();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationRecipient;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationRecipient>
 */
class NotificationRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRecipient::class);
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this
            ->createQueryBuilder('nr')
            ->select('COUNT(nr.id)')
            ->where('nr.user = :user')
            ->andWhere('nr.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Keyset-paginated page of a user's notifications, newest first, with
     * content eager-loaded to avoid N+1.
     *
     * @return array<int, NotificationRecipient>
     */
    public function findPageForUser(User $user, int $limit, ?\DateTimeInterface $beforeCreatedAt = null, ?int $beforeId = null): array
    {
        $qb = $this
            ->createQueryBuilder('nr')
            ->addSelect('n')
            ->join('nr.notification', 'n')
            ->where('nr.user = :user')
            ->setParameter('user', $user)
            ->orderBy('nr.createdAt', 'DESC')
            ->addOrderBy('nr.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $beforeCreatedAt && null !== $beforeId) {
            $qb
                ->andWhere('nr.createdAt < :beforeCreatedAt OR (nr.createdAt = :beforeCreatedAt AND nr.id < :beforeId)')
                ->setParameter('beforeCreatedAt', $beforeCreatedAt)
                ->setParameter('beforeId', $beforeId);
        }

        return $qb->getQuery()->getResult();
    }

    public function markAllReadForUser(User $user): int
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->update(NotificationRecipient::class, 'nr')
            ->set('nr.isRead', 'true')
            ->set('nr.readAt', ':now')
            ->where('nr.user = :user')
            ->andWhere('nr.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    public function deleteByUser(User $user): void
    {
        $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->delete(NotificationRecipient::class, 'nr')
            ->where('nr.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /** Deletes read recipient rows created before $before. Returns the number of rows removed. */
    public function deleteReadOlderThan(\DateTimeInterface $before): int
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->delete(NotificationRecipient::class, 'nr')
            ->where('nr.isRead = true')
            ->andWhere('nr.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    public function countReadOlderThan(\DateTimeInterface $before): int
    {
        return (int) $this
            ->createQueryBuilder('nr')
            ->select('COUNT(nr.id)')
            ->where('nr.isRead = true')
            ->andWhere('nr.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Deletes unread recipient rows created before $before. Without this, a
     * dormant account's broadcast notifications (e.g. monthly ranking updates)
     * would accumulate forever, since unread rows never age out via
     * deleteReadOlderThan().
     */
    public function deleteUnreadOlderThan(\DateTimeInterface $before): int
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->delete(NotificationRecipient::class, 'nr')
            ->where('nr.isRead = false')
            ->andWhere('nr.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    public function countUnreadOlderThan(\DateTimeInterface $before): int
    {
        return (int) $this
            ->createQueryBuilder('nr')
            ->select('COUNT(nr.id)')
            ->where('nr.isRead = false')
            ->andWhere('nr.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

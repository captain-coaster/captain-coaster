<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Enum\NotificationType;
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

    /**
     * An existing content row with the exact same (type, message, parameter),
     * for {@see NotificationService::send()} to reuse instead of creating a
     * duplicate — e.g. every "rating1 badge" notification is the same content
     * regardless of which user earned it.
     */
    public function findMatching(NotificationType $type, string $message, ?string $parameter): ?Notification
    {
        return $this->findOneBy(['type' => $type, 'message' => $message, 'parameter' => $parameter]);
    }

    private const string ORPHAN_CONDITION = 'NOT EXISTS (SELECT 1 FROM App\Entity\NotificationRecipient nr WHERE nr.notification = n)';

    /**
     * Deletes content rows created before $before that no recipient row
     * points to any more (i.e. every recipient already aged out via
     * {@see NotificationRecipientRepository}). The $before cutoff also keeps
     * this from racing a broadcast still being fanned out, whose Notification
     * row briefly has zero recipients between creation and the first flush.
     * Returns the number of rows removed.
     */
    public function deleteOrphanedBefore(\DateTimeInterface $before): int
    {
        return $this
            ->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\Notification n WHERE n.createdAt < :before AND '.self::ORPHAN_CONDITION)
            ->setParameter('before', $before)
            ->execute();
    }

    public function countOrphanedBefore(\DateTimeInterface $before): int
    {
        return (int) $this
            ->getEntityManager()
            ->createQuery('SELECT COUNT(n.id) FROM App\Entity\Notification n WHERE n.createdAt < :before AND '.self::ORPHAN_CONDITION)
            ->setParameter('before', $before)
            ->getSingleScalarResult();
    }
}

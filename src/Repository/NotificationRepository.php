<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
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

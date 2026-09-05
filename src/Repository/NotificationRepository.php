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
}

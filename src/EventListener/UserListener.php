<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: User::class)]
class UserListener
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function postUpdate(User $user, PostUpdateEventArgs $event): void
    {
        if (!$user->isEnabled()) {
            $user->setEmailNotification(false);
            $this->em->persist($user);
            $this->em->flush();
        }
    }
}

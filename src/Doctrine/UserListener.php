<?php

namespace App\Doctrine;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;

class UserListener
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        /** @var User $user */
        $user = $args->getEntity();

        if (!$user instanceof User) {
            return;
        }

        // make sure we no longer send emails to disabled / banned users
        if (!$user->isEnabled()) {
            $user->setEmailNotification(false);
            $this->em->persist($user);
            $this->em->flush();
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\User;

final class BadgeAwardedEvent
{
    public function __construct(
        public readonly User $user,
        public readonly string $badgeName,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Message;

final class SendNotificationEmailMessage
{
    public function __construct(
        public readonly int $recipientId,
    ) {
    }
}

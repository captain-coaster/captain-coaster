<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case Ranking = 'ranking';
    case Announcement = 'announcement';

    public function route(): string
    {
        return match ($this) {
            self::Ranking => 'ranking_index',
            self::Announcement => 'profile_settings',
        };
    }

    /** ux_icon() name for the notification list's type icon. */
    public function icon(): string
    {
        return match ($this) {
            self::Ranking => 'lucide:chart-column',
            self::Announcement => 'lucide:info',
        };
    }
}

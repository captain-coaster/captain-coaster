<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case Ranking = 'ranking';
    case Badge = 'badge';
    case Announcement = 'announcement';

    public function route(): string
    {
        return match ($this) {
            self::Ranking => 'ranking_index',
            self::Badge => 'profile',
            self::Announcement => 'profile_settings',
        };
    }

    public function emailByDefault(): bool
    {
        return match ($this) {
            self::Ranking, self::Announcement => false,
            self::Badge => true,
        };
    }

    /**
     * Whether `Notification::parameter` is itself a translation key (e.g. a badge
     * name) rather than raw display text (e.g. a coaster name) — determines
     * whether it should be run through the translator before display.
     */
    public function parameterIsTranslationKey(): bool
    {
        return match ($this) {
            self::Badge => true,
            self::Ranking, self::Announcement => false,
        };
    }

    /** ux_icon() name for the notification list's type icon. */
    public function icon(): string
    {
        return match ($this) {
            self::Ranking => 'heroicons:chart-bar',
            self::Badge => 'heroicons-solid:star',
            self::Announcement => 'heroicons:information-circle',
        };
    }

    /** Background color class for the type icon's circle, e.g. `bg-primary`. */
    public function iconColorClass(): string
    {
        return match ($this) {
            self::Ranking => 'bg-primary',
            self::Badge => 'bg-success-400',
            self::Announcement => 'bg-warning-400',
        };
    }
}

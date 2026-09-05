<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case Ranking = 'ranking';
    case Badge = 'badge';

    public function route(): string
    {
        return match ($this) {
            self::Ranking => 'ranking_index',
            self::Badge => 'profile',
        };
    }

    public function emailByDefault(): bool
    {
        return match ($this) {
            self::Ranking => false,
            self::Badge => true,
        };
    }

    /** @return list<'in_app'|'email'> */
    public function channels(): array
    {
        return match ($this) {
            self::Ranking, self::Badge => ['in_app', 'email'],
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
            self::Ranking => false,
        };
    }
}

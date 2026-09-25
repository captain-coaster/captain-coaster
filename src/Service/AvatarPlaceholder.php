<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Deterministic initials-on-color placeholder for users without a profile
 * picture. The colors live in assets/styles/tokens.css as `--avatar-1` to
 * `--avatar-8`; this only picks the initials and the slot.
 */
final class AvatarPlaceholder
{
    public const int SLOT_COUNT = 8;

    public function initials(string $displayName): string
    {
        $words = preg_split('/\s+/', trim($displayName), -1, \PREG_SPLIT_NO_EMPTY);

        $initials = '';
        foreach ($words ?: [] as $word) {
            if (!preg_match('/[\p{L}\p{N}]/u', $word, $match)) {
                continue;
            }

            $initials .= $match[0];
            if (2 === mb_strlen($initials)) {
                break;
            }
        }

        return '' === $initials ? '?' : mb_strtoupper($initials);
    }

    /** 1-based, matching the `--avatar-N` token names. */
    public function slot(int $seed): int
    {
        return $seed % self::SLOT_COUNT + 1;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Deterministic initials + color for users without a profile picture.
 */
final class AvatarPlaceholder
{
    /** Same brand colors as assets/styles/tokens.css, all already paired with white text elsewhere (colors.css's bg-* classes). */
    private const array PALETTE = [
        '#2196f3', // primary
        '#1e88e5', // primary-600
        '#00bcd4', // info
        '#4caf50', // success
        '#66bb6a', // success-400
        '#ff5722', // warning
        '#aa3510', // warning-deep
        '#f44336', // danger
        '#26a69a', // teal-400
    ];

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

    public function color(int $seed): string
    {
        return self::PALETTE[$seed % \count(self::PALETTE)];
    }
}

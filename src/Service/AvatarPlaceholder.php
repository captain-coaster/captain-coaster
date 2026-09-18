<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Deterministic initials-on-color placeholder avatar for users without a
 * profile picture, rendered as an inline SVG data URI so the markup stays a
 * plain <img> -- it then inherits every existing img-specific CSS rule
 * (navbar sizing, ring borders, max-height caps) exactly like a real photo,
 * instead of a hand-built element needing its own layout rules.
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

    public function dataUri(string $displayName, int $seed): string
    {
        $initials = $this->initials($displayName);
        $color = $this->color($seed);
        // A single letter reads small at this scale next to two -- bump it up so both feel the same visual weight.
        $fontSize = 1 === mb_strlen($initials) ? 17 : 14;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
            .'<circle cx="20" cy="20" r="20" fill="'.$color.'"/>'
            .'<text x="20" y="20" dy=".35em" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="600" font-size="'.$fontSize.'" fill="#fff">'.htmlspecialchars($initials).'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

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

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
    /**
     * Palette primitives from assets/styles/tokens.css that carry white initials at >= 4.5:1.
     * Hex because the SVG is rendered as an <img> and can't read CSS variables;
     * AvatarPlaceholderTest checks each value still exists in tokens.css.
     */
    private const array PALETTE = [
        '#2359AD', // blue-700
        '#23634E', // green-700
        '#AB4138', // coral-600
        '#4767A3', // blue-500
        '#78500A', // amber-800
        '#405467', // slate-600
        '#19458C', // blue-800
        '#28343A', // logo-ink
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

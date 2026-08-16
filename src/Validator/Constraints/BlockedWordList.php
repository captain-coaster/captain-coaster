<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

/**
 * Words with no legitimate non-insulting use in a coaster review, chosen for
 * real frequency in the review corpus (English/French only) rather than
 * theoretical coverage. See
 * docs/superpowers/specs/2026-08-16-slur-and-profanity-block-design.md
 * for the data behind this list and why other candidates (identity slurs,
 * "con", "retard", ES/DE words) were deliberately excluded.
 */
final class BlockedWordList
{
    /**
     * Regex alternatives, matched with word boundaries (case-insensitive).
     * `\w*` stems catch common inflections (fuck/fucking/fucker/fucked).
     * "salope" has no `\w*` suffix deliberately: "salopette" (French for
     * dungarees) is an innocent word that starts with the same letters.
     *
     * @var list<string>
     */
    private const array PATTERNS = [
        'fuck\w*',
        'putain',
        'goddamn\w*',
        'bitch\w*',
        'connard[e]?s?',
        'asshole\w*',
        'salope',
    ];

    public static function matches(string $text): bool
    {
        if ('' === $text) {
            return false;
        }

        $regex = '/\b(?:'.implode('|', self::PATTERNS).')\b/iu';

        return 1 === preg_match($regex, $text);
    }
}

<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

/**
 * Heavy profanity and insults, refused when a review is submitted: words that are badly received
 * and never needed to give an opinion of a ride. Everyday swearing stays allowed ("merde",
 * "shit", "putain", "bordel", "Scheiße", "joder"): the review policy publishes it.
 *
 * Words are matched across languages, since a review's language is only known after analysis.
 * Masked spellings are caught: between the first and the last letter, any letter can be a symbol
 * ("f*ck", "f**k"), and past the first one a letter can be its leetspeak lookalike ("fvck",
 * "b!tch"). A letter in brackets may be left out ("fck"). The first and last letters are never
 * symbols, so "of..." or "f**" are not read as a word.
 */
final class BlockedWordList
{
    /**
     * A trailing "+" allows any ending (fuck → fucking, fucker); "~" in front matches inside a
     * longer word (motherfucker). Spaces stand for one or more spaces.
     */
    private const array WORDS = [
        'en' => ['~f(u)ck+', 'cunt+', 'bitch+', 'asshole+', 'dickhead+', 'wanker+', 'twat+'],
        'fr' => ['encul+', 'salope', 'salopes', 'connard+', 'connasse+', 'pute', 'putes', 'nique ta mere', 'nique sa mere'],
        // Not "fick" alone: Swedish for "got".
        'de' => ['ficken', 'fickt', 'gefickt', 'fotze+', 'wichser+', 'arschloch+', 'hurensohn+'],
        'es' => ['hijo de puta', 'hijos de puta', 'puta', 'putas', 'puto', 'putos', 'gilipollas'],
    ];

    /** Characters a letter can be written with, past the first one. */
    private const array LOOKALIKES = ['a' => 'a4@', 'e' => 'eéèê3', 'i' => 'i1!', 'o' => 'o0', 's' => 's5$', 'u' => 'uv', 't' => 't7'];

    private const string MASK = '*#@$%!';

    /** The first blocked word in the text, as written, or null. */
    public static function find(string $text): ?string
    {
        if ('' === $text) {
            return null;
        }

        // preg_match() fails on malformed UTF-8: fail closed rather than let it through.
        $result = preg_match(self::regex(), $text, $match);
        if (false === $result) {
            return '';
        }

        return 1 === $result ? $match[0] : null;
    }

    private static function regex(): string
    {
        static $regex = null;

        return $regex ??= '/'.implode('|', array_map(self::pattern(...), array_merge(...array_values(self::WORDS)))).'/iu';
    }

    private static function pattern(string $word): string
    {
        $inside = str_starts_with($word, '~');
        $anyEnding = str_ends_with($word, '+');
        $word = trim($word, '~+');

        $pattern = '';
        foreach (explode(' ', $word) as $n => $part) {
            preg_match_all('/\(?([a-z])\)?/', $part, $letters, \PREG_SET_ORDER);
            $last = \count($letters) - 1;
            $pattern .= $n > 0 ? '\s+' : '';
            foreach ($letters as $index => [$token, $char]) {
                $chars = match ($index) {
                    0 => $char,
                    $last => self::LOOKALIKES[$char] ?? $char,
                    default => (self::LOOKALIKES[$char] ?? $char).self::MASK,
                };
                $pattern .= '['.preg_quote($chars, '/').']'.('(' === $token[0] ? '?' : '');
            }
        }

        return ($inside ? '' : '(?<![\p{L}\p{N}])').$pattern.($anyEnding ? '\p{L}*' : '(?![\p{L}\p{N}])');
    }
}

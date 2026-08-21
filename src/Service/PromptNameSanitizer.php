<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Strips characters that could be used for prompt injection from a name before it's
 * interpolated into an AI prompt, while preserving any Unicode letter/digit (accents
 * included).
 */
final class PromptNameSanitizer
{
    public static function sanitize(string $name): string
    {
        // preg_replace with the /u modifier returns null (not the original string) when
        // given malformed UTF-8, instead of throwing - fall back to the original name
        // rather than silently dropping it from the prompt.
        return preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $name) ?? $name;
    }
}

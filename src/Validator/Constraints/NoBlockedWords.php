<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * A review text without heavy profanity (BlockedWordList).
 */
#[\Attribute]
class NoBlockedWords extends Constraint
{
    public string $message = 'blocked_words.review';
}

<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NoBlockedWords extends Constraint
{
    public string $message = 'blocked_words.review';
}

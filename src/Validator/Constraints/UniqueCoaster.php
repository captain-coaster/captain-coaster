<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class UniqueCoaster extends Constraint
{
    public string $message = 'top_coaster.duplicate';
}

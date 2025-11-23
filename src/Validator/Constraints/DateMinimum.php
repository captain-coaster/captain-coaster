<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DateMinimum extends Constraint
{
    public string $message = 'date_minimum.error';
}

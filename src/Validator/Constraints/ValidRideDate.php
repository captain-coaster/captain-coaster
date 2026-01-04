<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidRideDate extends Constraint
{
    public string $beforeOpeningMessage = 'ride_date.before_opening';
    public string $afterClosingMessage = 'ride_date.after_closing';
    public string $futureMessage = 'ride_date.future';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}

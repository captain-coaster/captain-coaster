<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ModelManufacturer extends Constraint
{
    public string $message = 'model_manufacturer.wrong';
}

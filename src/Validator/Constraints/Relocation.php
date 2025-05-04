<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class Relocation extends Constraint
{
    public string $short = 'relocation.short';
    public string $duplicate = 'relocation.duplicate';
}

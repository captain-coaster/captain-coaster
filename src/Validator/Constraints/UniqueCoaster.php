<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UniqueCoaster extends Constraint
{
    public $message = 'top_coaster.duplicate';
}

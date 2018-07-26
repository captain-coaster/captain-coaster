<?php

namespace BddBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UniqueCoaster extends Constraint
{
    public $message = 'liste_coaster.duplicate';
}

<?php

namespace BddBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class ReCaptcha extends Constraint
{
    public $message = 'contact.recaptcha';
}

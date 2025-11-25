<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class DateMinimumValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof DateMinimum) {
            throw new UnexpectedTypeException($constraint, DateMinimum::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_array($value) && !$value instanceof \DateTime) {
            throw new UnexpectedTypeException($value, 'DateTime');
        }

        if ((int) $value->format('Y') < 1800) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();

            return;
        }
    }
}

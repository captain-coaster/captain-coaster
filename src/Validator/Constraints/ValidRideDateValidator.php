<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\RiddenCoaster;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidRideDateValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidRideDate) {
            throw new UnexpectedTypeException($constraint, ValidRideDate::class);
        }

        if (!$value instanceof RiddenCoaster) {
            throw new UnexpectedValueException($value, RiddenCoaster::class);
        }

        $riddenAt = $value->getRiddenAt();
        $coaster = $value->getCoaster();

        // If no ride date is set, validation passes
        if (null === $riddenAt) {
            return;
        }

        // Check if ride date is in the future
        $today = new \DateTime();
        $today->setTime(23, 59, 59);
        if ($riddenAt > $today) {
            $this->context->buildViolation($constraint->futureMessage)
                ->atPath('riddenAt')
                ->addViolation();

            return;
        }

        // Check if ride date is before coaster opening
        $openingDate = $coaster->getOpeningDate();
        if (null !== $openingDate && $riddenAt < $openingDate) {
            $this->context->buildViolation($constraint->beforeOpeningMessage)
                ->atPath('riddenAt')
                ->addViolation();

            return;
        }

        // Check if ride date is after coaster closing
        $closingDate = $coaster->getClosingDate();
        if (null !== $closingDate && $riddenAt > $closingDate) {
            $this->context->buildViolation($constraint->afterClosingMessage)
                ->atPath('riddenAt')
                ->addViolation();
        }
    }
}

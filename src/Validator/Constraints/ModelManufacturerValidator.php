<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ModelManufacturerValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ModelManufacturer) {
            throw new UnexpectedTypeException($constraint, ModelManufacturer::class);
        }

        if (null === $value) {
            return;
        }

        $currentCoaster = $this->context->getObject();
        if ($value->getManufacturer() && $currentCoaster->getManufacturer()->getId() !== $value->getManufacturer()->getId()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('%model%', $value->getName())
                ->setParameter('%manufacturer%', $currentCoaster->getManufacturer()->getName())
                ->addViolation();

            return;
        }
    }
}

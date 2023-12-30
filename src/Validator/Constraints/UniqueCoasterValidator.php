<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\TopCoaster;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueCoasterValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueCoaster) {
            throw new UnexpectedTypeException($constraint, UniqueCoaster::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_array($value) && !$value instanceof \IteratorAggregate) {
            throw new UnexpectedTypeException($value, 'IteratorAggregate');
        }

        $collectionElements = [];
        /** @var TopCoaster $element */
        foreach ($value as $element) {
            if (!$element instanceof TopCoaster) {
                throw new UnexpectedTypeException($element, 'IteratorAggregate');
            }

            if (\in_array($element->getCoaster(), $collectionElements, true)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('%coaster%', $this->formatValue($element->getCoaster(), 2))
                    ->addViolation();

                return;
            }
            $collectionElements[] = $element->getCoaster();
        }
    }
}

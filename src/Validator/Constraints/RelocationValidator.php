<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\RelocationCoaster;
use App\Repository\RelocationRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

#[\AllowDynamicProperties]
class RelocationValidator extends ConstraintValidator
{
    public function __construct(RelocationRepository $relocationRepository)
    {
        $this->relocationRepository = $relocationRepository;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Relocation) {
            throw new UnexpectedTypeException($constraint, Relocation::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_array($value) && !$value instanceof \IteratorAggregate) {
            throw new UnexpectedTypeException($value, 'IteratorAggregate');
        }

        if (\count($value) < 2) {
            $this->context->buildViolation($constraint->short)->addViolation();

            return;
        }

        $collectionElements = [];
        foreach ($value as $element) {
            if (!$element instanceof RelocationCoaster) {
                throw new UnexpectedTypeException($element, 'IteratorAggregate');
            }

            $currentRelocation = $element->getRelocation();
            $relocations = $this->relocationRepository->findAnotherRelocation($element->getCoaster());

            foreach ($relocations as $relocation) {
                if ($relocation->getRelocation() !== $currentRelocation) {
                    $this->context->buildViolation($constraint->duplicate)
                        ->setParameter('%coaster%', $this->formatValue($element->getCoaster(), 2))
                        ->addViolation();

                    return;
                }
            }

            $collectionElements[] = $element->getCoaster();
        }
    }
}

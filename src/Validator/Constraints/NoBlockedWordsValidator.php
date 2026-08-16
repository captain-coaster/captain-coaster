<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoBlockedWordsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoBlockedWords) {
            throw new UnexpectedTypeException($constraint, NoBlockedWords::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (BlockedWordList::matches($value)) {
            $this->logger->info('Review submission blocked: contains a blocked word', [
                'review_text' => $value,
            ]);

            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}

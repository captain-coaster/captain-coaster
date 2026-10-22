<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoBlockedWordsValidator extends ConstraintValidator
{
    /** $moderationLogger: the "moderation" channel, written to its own file in prod. */
    public function __construct(
        private readonly LoggerInterface $moderationLogger,
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

        if (null === $word = BlockedWordList::find($value)) {
            return;
        }

        $this->moderationLogger->info('Review refused: heavy profanity', ['word' => $word, 'review_text' => $value]);
        $this->context->buildViolation($constraint->message)
            ->setParameter('word', $word)
            ->addViolation();
    }
}

<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NoBlockedWordsValidator extends ConstraintValidator
{
    /**
     * Injected via Symfony/MonologBundle's parameter-name channel autowiring:
     * a constructor argument named "$moderationLogger" resolves to the
     * "moderation" monolog channel (see config/packages/monolog.yaml),
     * which has its own handler outside the main fingers_crossed buffer so
     * these info-level records reach disk in prod instead of being
     * silently discarded.
     */
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

        if (BlockedWordList::matches($value)) {
            // The validator only ever sees the raw string value being
            // validated (it has no access to the request, the entity, or
            // the current user), so the log context is limited to the
            // review text itself — there's no user_id/coaster_id available
            // here without a larger refactor of how this constraint is
            // invoked.
            $this->moderationLogger->info('Review submission blocked: contains a blocked word', [
                'review_text' => $value,
            ]);

            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}

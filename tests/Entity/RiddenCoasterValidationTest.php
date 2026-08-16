<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Validator\Constraints\NoBlockedWordsValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RiddenCoasterValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        // Stub out doctrine's UniqueEntity validator (service id
        // "doctrine.orm.validator.unique", declared class-level on
        // RiddenCoaster): it needs a real ManagerRegistry/EntityManager,
        // which these unit tests don't have. It's irrelevant to what's
        // under test here (the NoBlockedWords group scoping), so it's
        // stubbed to never flag a violation.
        $noopValidator = new class extends ConstraintValidator {
            public function validate(mixed $value, Constraint $constraint): void
            {
            }
        };

        $validatorLocator = new ServiceLocator([
            NoBlockedWordsValidator::class => static fn (): NoBlockedWordsValidator => new NoBlockedWordsValidator(new NullLogger()),
            'doctrine.orm.validator.unique' => static fn () => $noopValidator,
        ]);

        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ContainerConstraintValidatorFactory($validatorLocator))
            ->getValidator();
    }

    public function testReviewPropertyRejectsBlockedWord(): void
    {
        $violations = $this->validator->validatePropertyValue(
            RiddenCoaster::class,
            'review',
            'This coaster is fucking amazing',
            ['review_text']
        );

        $this->assertCount(1, $violations);
        $this->assertSame('blocked_words.review', $violations->get(0)->getMessageTemplate());
    }

    public function testReviewPropertyAcceptsCleanText(): void
    {
        $violations = $this->validator->validatePropertyValue(
            RiddenCoaster::class,
            'review',
            'This coaster is absolutely amazing, best drop ever',
            ['review_text']
        );

        $this->assertCount(0, $violations);
    }

    public function testReviewPropertyAcceptsNull(): void
    {
        $violations = $this->validator->validatePropertyValue(
            RiddenCoaster::class,
            'review',
            null,
            ['review_text']
        );

        $this->assertCount(0, $violations);
    }

    /**
     * Regression test for the whole-branch review finding: the constraint
     * must be scoped to the "review_text" group, not the implicit
     * "Default" group, otherwise validating a RiddenCoaster under Default
     * (e.g. RatingCoasterController's AJAX rating/date-only path, or
     * EasyAdmin's save) fails for any row whose review already contains a
     * blocked word — even when review itself isn't being changed.
     */
    public function testReviewPropertyIsNotValidatedUnderDefaultGroup(): void
    {
        $violations = $this->validator->validatePropertyValue(
            RiddenCoaster::class,
            'review',
            'This coaster is fucking amazing'
        );

        $this->assertCount(0, $violations);
    }

    /**
     * Mirrors RatingCoasterController::editAction(), which calls
     * $validator->validate($rating) (implicit Default group) on the AJAX
     * rating/ride-date path that never touches "review". Before the group
     * fix, a RiddenCoaster whose review already contained a blocked word
     * (e.g. one of the ~157 pre-existing rows called out in the design
     * spec) would fail this validate() call even though only value/riddenAt
     * changed, silently blocking legitimate rating edits.
     */
    public function testWholeEntityValidationUnderDefaultGroupIgnoresBlockedReview(): void
    {
        $rating = new RiddenCoaster();
        $rating->setCoaster(new Coaster());
        $rating->setUser(new User());
        $rating->setValue(4.5);
        $rating->setReview('This coaster is fucking amazing');

        $violations = $this->validator->validate($rating);

        $this->assertCount(0, $violations);
    }
}

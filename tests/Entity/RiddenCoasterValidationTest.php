<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\RiddenCoaster;
use App\Validator\Constraints\NoBlockedWordsValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RiddenCoasterValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $validatorLocator = new ServiceLocator([
            NoBlockedWordsValidator::class => static fn (): NoBlockedWordsValidator => new NoBlockedWordsValidator(new NullLogger()),
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
            'This coaster is fucking amazing'
        );

        $this->assertCount(1, $violations);
        $this->assertSame('blocked_words.review', $violations->get(0)->getMessageTemplate());
    }

    public function testReviewPropertyAcceptsCleanText(): void
    {
        $violations = $this->validator->validatePropertyValue(
            RiddenCoaster::class,
            'review',
            'This coaster is absolutely amazing, best drop ever'
        );

        $this->assertCount(0, $violations);
    }

    public function testReviewPropertyAcceptsNull(): void
    {
        $violations = $this->validator->validatePropertyValue(
            RiddenCoaster::class,
            'review',
            null
        );

        $this->assertCount(0, $violations);
    }
}

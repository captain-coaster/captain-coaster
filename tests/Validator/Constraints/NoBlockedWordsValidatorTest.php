<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\NoBlockedWords;
use App\Validator\Constraints\NoBlockedWordsValidator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<NoBlockedWordsValidator> */
class NoBlockedWordsValidatorTest extends ConstraintValidatorTestCase
{
    private ?LoggerInterface $logger = null;

    protected function createValidator(): NoBlockedWordsValidator
    {
        return new NoBlockedWordsValidator($this->logger ?? new NullLogger());
    }

    public function testATextWithHeavyProfanityIsRefusedNamingTheWord(): void
    {
        $this->validator->validate('That launch is f*cking great', new NoBlockedWords());

        $this->buildViolation('blocked_words.review')
            ->setParameter('word', 'f*cking')
            ->assertRaised();
    }

    public function testARefusalIsLoggedOnTheModerationChannel(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('info')->with('Review refused: heavy profanity', $this->callback(static fn (array $context): bool => 'fuck' === $context['word']));
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate('fuck', new NoBlockedWords());
    }

    public function testEverydaySwearingAndEmptyTextPass(): void
    {
        $this->validator->validate('Putain, c\'est de la merde', new NoBlockedWords());
        $this->validator->validate(null, new NoBlockedWords());
        $this->validator->validate('', new NoBlockedWords());

        $this->assertNoViolation();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\NoBlockedWords;
use App\Validator\Constraints\NoBlockedWordsValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class NoBlockedWordsValidatorTest extends TestCase
{
    private NoBlockedWordsValidator $validator;
    private ExecutionContextInterface&MockObject $context;
    private ConstraintViolationBuilderInterface&MockObject $violationBuilder;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->validator = new NoBlockedWordsValidator($this->logger);
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);

        $this->validator->initialize($this->context);
    }

    public function testValidateBuildsViolationForBlockedWord(): void
    {
        $constraint = new NoBlockedWords();

        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->violationBuilder);

        $this->validator->validate('This coaster is fucking amazing', $constraint);
    }

    public function testValidateLogsBlockedSubmission(): void
    {
        $constraint = new NoBlockedWords();

        $this->context->method('buildViolation')->willReturn($this->violationBuilder);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Review submission blocked: contains a blocked word', $this->anything());

        $this->validator->validate('This coaster is fucking amazing', $constraint);
    }

    public function testValidatePassesForCleanText(): void
    {
        $constraint = new NoBlockedWords();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->logger->expects($this->never())
            ->method('info');

        $this->validator->validate('This coaster is absolutely amazing', $constraint);
    }

    public function testValidatePassesForNull(): void
    {
        $constraint = new NoBlockedWords();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate(null, $constraint);
    }

    public function testValidatePassesForEmptyString(): void
    {
        $constraint = new NoBlockedWords();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate('', $constraint);
    }

    public function testValidateThrowsForWrongConstraintType(): void
    {
        $wrongConstraint = $this->createMock(Constraint::class);

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('some text', $wrongConstraint);
    }

    public function testValidateThrowsForNonStringValue(): void
    {
        $constraint = new NoBlockedWords();

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(42, $constraint);
    }
}

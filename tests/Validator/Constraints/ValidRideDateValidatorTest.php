<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Validator\Constraints\ValidRideDate;
use App\Validator\Constraints\ValidRideDateValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidRideDateValidatorTest extends TestCase
{
    private ValidRideDateValidator $validator;
    private ExecutionContextInterface&MockObject $context;
    private ConstraintViolationBuilderInterface&MockObject $violationBuilder;

    protected function setUp(): void
    {
        $this->validator = new ValidRideDateValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        
        $this->validator->initialize($this->context);
    }

    public function testValidatePassesWithNullRiddenAt(): void
    {
        $riddenCoaster = new RiddenCoaster();
        $riddenCoaster->setCoaster(new Coaster());
        $riddenCoaster->setUser(new User());
        $riddenCoaster->setRiddenAt(null);

        $constraint = new ValidRideDate();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($riddenCoaster, $constraint);
    }

    public function testValidateFailsWithFutureDate(): void
    {
        $riddenCoaster = new RiddenCoaster();
        $riddenCoaster->setCoaster(new Coaster());
        $riddenCoaster->setUser(new User());
        $riddenCoaster->setRiddenAt(new \DateTime('+1 day'));

        $constraint = new ValidRideDate();

        $this->violationBuilder->expects($this->once())
            ->method('atPath')
            ->with('riddenAt')
            ->willReturnSelf();
        
        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->futureMessage)
            ->willReturn($this->violationBuilder);

        $this->validator->validate($riddenCoaster, $constraint);
    }

    public function testValidateFailsWithDateBeforeOpening(): void
    {
        $coaster = new Coaster();
        $coaster->setOpeningDate(new \DateTime('2020-01-01'));

        $riddenCoaster = new RiddenCoaster();
        $riddenCoaster->setCoaster($coaster);
        $riddenCoaster->setUser(new User());
        $riddenCoaster->setRiddenAt(new \DateTime('2019-12-31'));

        $constraint = new ValidRideDate();

        $this->violationBuilder->expects($this->once())
            ->method('atPath')
            ->with('riddenAt')
            ->willReturnSelf();
        
        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->beforeOpeningMessage)
            ->willReturn($this->violationBuilder);

        $this->validator->validate($riddenCoaster, $constraint);
    }

    public function testValidateFailsWithDateAfterClosing(): void
    {
        $coaster = new Coaster();
        $coaster->setClosingDate(new \DateTime('2020-12-31'));

        $riddenCoaster = new RiddenCoaster();
        $riddenCoaster->setCoaster($coaster);
        $riddenCoaster->setUser(new User());
        $riddenCoaster->setRiddenAt(new \DateTime('2021-01-01'));

        $constraint = new ValidRideDate();

        $this->violationBuilder->expects($this->once())
            ->method('atPath')
            ->with('riddenAt')
            ->willReturnSelf();
        
        $this->violationBuilder->expects($this->once())
            ->method('addViolation');

        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->afterClosingMessage)
            ->willReturn($this->violationBuilder);

        $this->validator->validate($riddenCoaster, $constraint);
    }

    public function testValidatePassesWithValidDate(): void
    {
        $coaster = new Coaster();
        $coaster->setOpeningDate(new \DateTime('2020-01-01'));
        $coaster->setClosingDate(new \DateTime('2020-12-31'));

        $riddenCoaster = new RiddenCoaster();
        $riddenCoaster->setCoaster($coaster);
        $riddenCoaster->setUser(new User());
        $riddenCoaster->setRiddenAt(new \DateTime('2020-06-15'));

        $constraint = new ValidRideDate();

        $this->context->expects($this->never())
            ->method('buildViolation');

        $this->validator->validate($riddenCoaster, $constraint);
    }
}
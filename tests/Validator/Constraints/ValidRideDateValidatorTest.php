<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\DTO\PartialDate;
use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Validator\Constraints\ValidRideDate;
use App\Validator\Constraints\ValidRideDateValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidRideDateValidatorTest extends TestCase
{
    private ValidRideDateValidator $validator;
    private ExecutionContextInterface&MockObject $context;

    protected function setUp(): void
    {
        $this->validator = new ValidRideDateValidator();
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->validator->initialize($this->context);
    }

    #[DataProvider('provideRideDates')]
    public function testRideDate(?string $opening, ?string $closing, ?string $riddenAt, ?string $expectedMessage): void
    {
        $coaster = new Coaster();
        $coaster->setOpening(null === $opening ? null : PartialDate::fromString($opening));
        $coaster->setClosing(null === $closing ? null : PartialDate::fromString($closing));

        $riddenCoaster = new RiddenCoaster();
        $riddenCoaster->setCoaster($coaster);
        $riddenCoaster->setUser(new User());
        $riddenCoaster->setRiddenAt(null === $riddenAt ? null : new \DateTime($riddenAt));

        if (null === $expectedMessage) {
            $this->context->expects($this->never())->method('buildViolation');
        } else {
            $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
            $violationBuilder->expects($this->once())->method('atPath')->with('riddenAt')->willReturnSelf();
            $violationBuilder->expects($this->once())->method('addViolation');
            $this->context->expects($this->once())->method('buildViolation')->with($expectedMessage)->willReturn($violationBuilder);
        }

        $this->validator->validate($riddenCoaster, new ValidRideDate());
    }

    /** @return iterable<string, array{?string, ?string, ?string, ?string}> */
    public static function provideRideDates(): iterable
    {
        yield 'no ride date' => ['2020-05-01', '2020-12-31', null, null];
        yield 'within the operating period' => ['2020-05-01', '2020-12-31', '2020-06-15', null];
        yield 'today' => [null, null, 'today', null];
        yield 'tomorrow' => [null, null, 'tomorrow', 'ride_date.future'];

        yield 'a preview, 90 days before the opening' => ['2020-05-01', null, '2020-02-01', null];
        yield '91 days before the opening' => ['2020-05-01', null, '2020-01-31', 'ride_date.before_opening'];
        yield 'opening known to the year, 90 days before January 1st' => ['2020', null, '2019-10-03', null];
        yield 'opening known to the year, earlier' => ['2020', null, '2019-10-02', 'ride_date.before_opening'];
        yield 'no opening date, 1950' => [null, null, '1950-01-01', null];
        yield 'no opening date, before 1950' => [null, null, '1949-12-31', 'ride_date.before_opening'];
        yield 'an old coaster, before 1950' => ['1925', null, '1949-12-31', 'ride_date.before_opening'];

        yield 'the closing day' => [null, '2020-09-30', '2020-09-30', null];
        yield 'the day after the closing' => [null, '2020-09-30', '2020-10-01', 'ride_date.after_closing'];
        yield 'closing known to the year, last day of it' => [null, '2020', '2020-12-31', null];
        yield 'closing known to the year, the next year' => [null, '2020', '2021-01-01', 'ride_date.after_closing'];
        yield 'closing known to the month, last day of it' => [null, '2020-02', '2020-02-29', null];
        yield 'closing known to the month, the next month' => [null, '2020-02', '2020-03-01', 'ride_date.after_closing'];
    }
}

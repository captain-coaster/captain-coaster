<?php

declare(strict_types=1);

namespace App\Tests\Form\Type;

use App\DTO\PartialDate;
use App\Enum\DatePrecision;
use App\Form\Type\PartialDateType;
use Symfony\Component\Form\Test\TypeTestCase;

class PartialDateTypeTest extends TypeTestCase
{
    public function testSubmitsADateWithItsPrecision(): void
    {
        $form = $this->factory->create(PartialDateType::class);
        $form->submit('2026-05');

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals(new PartialDate(new \DateTimeImmutable('2026-05-01'), DatePrecision::Month), $form->getData());
    }

    public function testAnEmptyFieldIsNoDate(): void
    {
        $form = $this->factory->create(PartialDateType::class, PartialDate::fromString('2026'));
        $this->assertSame('2026', $form->createView()->vars['value']);

        $form->submit('');

        $this->assertTrue($form->isSynchronized());
        $this->assertNull($form->getData());
    }

    public function testAnythingElseIsRefused(): void
    {
        $form = $this->factory->create(PartialDateType::class);
        $form->submit('mai 2026');

        $this->assertFalse($form->isSynchronized());
    }
}

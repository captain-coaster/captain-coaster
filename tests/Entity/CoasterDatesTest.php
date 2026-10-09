<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DTO\PartialDate;
use App\Entity\Coaster;
use App\Enum\DatePrecision;
use PHPUnit\Framework\TestCase;

class CoasterDatesTest extends TestCase
{
    public function testSetClosingStoresTheFirstDayAndThePrecision(): void
    {
        $coaster = new Coaster()->setClosing(PartialDate::fromString('2026'));

        $this->assertSame('2026-01-01', $coaster->getClosingDate()?->format('Y-m-d'));
        $this->assertSame(DatePrecision::Year, $coaster->getClosingDatePrecision());
        $this->assertSame('2026', (string) $coaster->getClosing());
        $this->assertSame('2026-12-31', $coaster->getLastRideDate()?->format('Y-m-d'));
    }

    public function testClearingADateResetsItsPrecision(): void
    {
        $coaster = new Coaster()->setOpening(PartialDate::fromString('2026'))->setOpening(null);

        $this->assertNull($coaster->getOpeningDate());
        $this->assertSame(DatePrecision::Day, $coaster->getOpeningDatePrecision());
    }

    /** Doctrine compares dates by identity: an unchanged admin save must not write the coaster. */
    public function testTheSameDayKeepsTheStoredDateObject(): void
    {
        $coaster = new Coaster()->setOpening(PartialDate::fromString('2026-05-17'));
        $stored = $coaster->getOpeningDate();

        $coaster->setOpening($coaster->getOpening());
        $this->assertSame($stored, $coaster->getOpeningDate());

        $coaster->setOpening(PartialDate::fromString('2026-05'));
        $this->assertNotSame($stored, $coaster->getOpeningDate());
        $this->assertSame(DatePrecision::Month, $coaster->getOpeningDatePrecision());
    }

    public function testFirstRideDate(): void
    {
        $coaster = new Coaster();
        $this->assertSame('1950-01-01', $coaster->getFirstRideDate()->format('Y-m-d'));

        $coaster->setOpening(PartialDate::fromString('2020-05-01'));
        $this->assertSame('2020-02-01', $coaster->getFirstRideDate()->format('Y-m-d'));
    }
}

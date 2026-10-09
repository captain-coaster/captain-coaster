<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\PartialDate;
use App\Enum\DatePrecision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PartialDateTest extends TestCase
{
    #[DataProvider('provideDates')]
    public function testFromString(string $text, string $storedDate, DatePrecision $precision): void
    {
        $date = PartialDate::fromString($text);

        $this->assertSame($storedDate, $date->date->format('Y-m-d H:i:s'));
        $this->assertSame($precision, $date->precision);
        $this->assertSame(trim($text), (string) $date);
    }

    /** @return iterable<string, array{string, string, DatePrecision}> */
    public static function provideDates(): iterable
    {
        yield 'year' => ['2026', '2026-01-01 00:00:00', DatePrecision::Year];
        yield 'month' => ['2026-05', '2026-05-01 00:00:00', DatePrecision::Month];
        yield 'day' => ['2026-05-17', '2026-05-17 00:00:00', DatePrecision::Day];
        yield 'a real January 1st' => ['2026-01-01', '2026-01-01 00:00:00', DatePrecision::Day];
        yield 'surrounding spaces' => [' 2026 ', '2026-01-01 00:00:00', DatePrecision::Year];
    }

    #[DataProvider('provideInvalidDates')]
    public function testFromStringRefusesAnythingElse(string $text): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PartialDate::fromString($text);
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidDates(): iterable
    {
        yield 'empty' => [''];
        yield 'two-digit year' => ['26'];
        yield 'month 13' => ['2026-13'];
        yield 'February 30th' => ['2026-02-30'];
        yield 'day first' => ['17/05/2026'];
        yield 'words' => ['spring 2026'];
    }
}

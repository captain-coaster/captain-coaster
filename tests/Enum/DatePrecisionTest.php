<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\DatePrecision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatePrecisionTest extends TestCase
{
    #[DataProvider('provideLastDays')]
    public function testLastDay(DatePrecision $precision, string $date, string $expected): void
    {
        $this->assertSame($expected, $precision->lastDay(new \DateTime($date))->format('Y-m-d'));
    }

    /** @return iterable<string, array{DatePrecision, string, string}> */
    public static function provideLastDays(): iterable
    {
        yield 'day' => [DatePrecision::Day, '2026-05-17', '2026-05-17'];
        yield 'month' => [DatePrecision::Month, '2026-04-01', '2026-04-30'];
        yield 'leap February' => [DatePrecision::Month, '2024-02-01', '2024-02-29'];
        yield 'year' => [DatePrecision::Year, '2026-01-01', '2026-12-31'];
    }
}

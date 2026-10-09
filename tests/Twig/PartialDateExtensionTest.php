<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\DTO\PartialDate;
use App\Twig\PartialDateExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PartialDateExtensionTest extends TestCase
{
    #[DataProvider('provideDates')]
    public function testFormat(string $date, string $locale, string $expected): void
    {
        $this->assertSame($expected, new PartialDateExtension()->format(PartialDate::fromString($date), $locale));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function provideDates(): iterable
    {
        yield 'year' => ['2026', 'en', '2026'];
        yield 'a year is not January 1st' => ['2026', 'fr', '2026'];
        yield 'month, English' => ['2026-05', 'en', 'May 2026'];
        yield 'month, French' => ['2026-05', 'fr', 'mai 2026'];
        yield 'month, German' => ['2026-03', 'de', 'März 2026'];
        yield 'month, Spanish' => ['2026-05', 'es', 'mayo 2026'];
        yield 'day, English' => ['2026-05-17', 'en', '5/17/26'];
        yield 'day, French' => ['2026-05-17', 'fr', '17/05/2026'];
        yield 'a real January 1st' => ['2026-01-01', 'fr', '01/01/2026'];
    }

    public function testNoDate(): void
    {
        $this->assertSame('', new PartialDateExtension()->format(null));
    }
}

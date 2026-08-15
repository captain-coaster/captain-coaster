<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\ShortNumberExtension;
use PHPUnit\Framework\TestCase;

class ShortNumberExtensionTest extends TestCase
{
    private ShortNumberExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ShortNumberExtension();
    }

    public function testFormatNumberUnder1000(): void
    {
        // Numbers below 1000 are shown as integers without decimals.
        $this->assertSame('0', $this->extension->formatNumber(0));
        $this->assertSame('1', $this->extension->formatNumber(1));
        $this->assertSame('914', $this->extension->formatNumber(914));
        $this->assertSame('999', $this->extension->formatNumber(999));
    }

    public function testFormatNumberThousandsDropsTrailingZeroes(): void
    {
        // Round thousands lose the ".0" suffix.
        $this->assertSame('1K', $this->extension->formatNumber(1000));
        $this->assertSame('681K', $this->extension->formatNumber(681_000));
        // Significant decimals stay.
        $this->assertSame('1.5K', $this->extension->formatNumber(1500));
        $this->assertSame('999.9K', $this->extension->formatNumber(999_900));
    }

    public function testFormatNumberMillions(): void
    {
        $this->assertSame('1M', $this->extension->formatNumber(1_000_000));
        $this->assertSame('2.5M', $this->extension->formatNumber(2_500_000));
        $this->assertSame('999M', $this->extension->formatNumber(999_000_000));
    }

    public function testFormatNumberBillions(): void
    {
        $this->assertSame('1B', $this->extension->formatNumber(1_000_000_000));
        $this->assertSame('5.5B', $this->extension->formatNumber(5_500_000_000));
    }

    public function testFormatNumberTrillions(): void
    {
        $this->assertSame('1T', $this->extension->formatNumber(1_000_000_000_000));
        $this->assertSame('10T', $this->extension->formatNumber(10_000_000_000_000));
    }

    public function testFormatNumberWithPrecision(): void
    {
        $this->assertSame('1.23K', $this->extension->formatNumber(1234, 2));
        $this->assertSame('1K', $this->extension->formatNumber(1234, 0));
    }

    public function testFormatNumberWithFloat(): void
    {
        $this->assertSame('1.5K', $this->extension->formatNumber(1500.5));
        $this->assertSame('2M', $this->extension->formatNumber(2_000_000.0));
    }

    public function testFormatNumberNegative(): void
    {
        // Negative numbers fall through to the K branch.
        $this->assertSame('-0.1K', $this->extension->formatNumber(-100));
        $this->assertSame('-1K', $this->extension->formatNumber(-1000));
    }
}

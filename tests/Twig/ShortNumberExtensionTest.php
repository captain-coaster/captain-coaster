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
        // formatNumber() defaults to \Locale::getDefault() when no locale is
        // passed explicitly — pin it so these assertions don't depend on
        // whatever locale a previous test left behind.
        \Locale::setDefault('en');
    }

    public function testFormatNumberUnder1000(): void
    {
        $this->assertSame('0.0', $this->extension->formatNumber(0));
        $this->assertSame('1.0', $this->extension->formatNumber(1));
        $this->assertSame('999.0', $this->extension->formatNumber(999));
    }

    public function testFormatNumberThousands(): void
    {
        $this->assertSame('1.0K', $this->extension->formatNumber(1000));
        $this->assertSame('1.5K', $this->extension->formatNumber(1500));
        $this->assertSame('999.9K', $this->extension->formatNumber(999_900));
    }

    public function testFormatNumberMillions(): void
    {
        $this->assertSame('1.0M', $this->extension->formatNumber(1_000_000));
        $this->assertSame('2.5M', $this->extension->formatNumber(2_500_000));
        $this->assertSame('999.0M', $this->extension->formatNumber(999_000_000));
    }

    public function testFormatNumberBillionsAndAboveFallBackToFullGroupedDigits(): void
    {
        // No "B"/"T" suffix: "billion" is 10^9 in English but 10^12 in
        // French/German/Spanish (long scale) — a hardcoded letter would be
        // silently wrong by a factor of 1000 in those locales.
        $this->assertSame('1,000,000,000', $this->extension->formatNumber(1_000_000_000));
        $this->assertSame('5,500,000,000', $this->extension->formatNumber(5_500_000_000));
        $this->assertSame('1,000,000,000,000', $this->extension->formatNumber(1_000_000_000_000));
    }

    public function testFormatNumberBillionsAndAboveUseLocaleGroupingSeparator(): void
    {
        // ICU's French grouping separator is U+202F (narrow no-break space),
        // not a plain ASCII space — easy to get wrong by eye, so spelled out.
        $narrowNoBreakSpace = "\u{202F}";
        $this->assertSame(
            "1{$narrowNoBreakSpace}000{$narrowNoBreakSpace}000{$narrowNoBreakSpace}000",
            $this->extension->formatNumber(1_000_000_000, 1, 'fr')
        );
    }

    public function testFormatNumberWithPrecision(): void
    {
        $this->assertSame('1.23K', $this->extension->formatNumber(1234, 2));
        $this->assertSame('1K', $this->extension->formatNumber(1234, 0));
    }

    public function testFormatNumberWithFloat(): void
    {
        $this->assertSame('1.5K', $this->extension->formatNumber(1500.5));
        $this->assertSame('2.0M', $this->extension->formatNumber(2_000_000.0));
    }

    public function testFormatNumberNegative(): void
    {
        // Negative numbers fall through to else clause
        $this->assertSame('-0.1K', $this->extension->formatNumber(-100));
        $this->assertSame('-1.0K', $this->extension->formatNumber(-1000));
    }

    public function testFormatNumberUsesLocaleDecimalSeparator(): void
    {
        $this->assertSame('1,5K', $this->extension->formatNumber(1500, 1, 'fr'));
        $this->assertSame('2,0M', $this->extension->formatNumber(2_000_000, 1, 'fr'));
    }

    public function testFormatNumberFollowsAmbientDefaultLocaleWhenNoneGiven(): void
    {
        \Locale::setDefault('fr');
        $this->assertSame('1,5K', $this->extension->formatNumber(1500));
    }
}

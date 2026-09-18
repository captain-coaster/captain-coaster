<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AvatarPlaceholder;
use PHPUnit\Framework\TestCase;

class AvatarPlaceholderTest extends TestCase
{
    private AvatarPlaceholder $avatarPlaceholder;

    protected function setUp(): void
    {
        $this->avatarPlaceholder = new AvatarPlaceholder();
    }

    public function testInitialsFromTwoWordsTakesFirstLetterOfEach(): void
    {
        $this->assertSame('JD', $this->avatarPlaceholder->initials('John Doe'));
    }

    public function testInitialsFromSingleWordTakesFirstLetterOnly(): void
    {
        $this->assertSame('W', $this->avatarPlaceholder->initials('WanExtraLife'));
    }

    public function testInitialsAreUppercased(): void
    {
        $this->assertSame('JD', $this->avatarPlaceholder->initials('john doe'));
    }

    public function testInitialsIgnoreExtraWhitespace(): void
    {
        $this->assertSame('JD', $this->avatarPlaceholder->initials('  John   Doe  '));
    }

    public function testInitialsFallBackToQuestionMarkForEmptyName(): void
    {
        $this->assertSame('?', $this->avatarPlaceholder->initials('   '));
    }

    public function testInitialsSkipPunctuationOnlyWords(): void
    {
        $this->assertSame('W', $this->avatarPlaceholder->initials('- WanExtraLife -'));
    }

    public function testColorIsDeterministicForSameSeed(): void
    {
        $this->assertSame(
            $this->avatarPlaceholder->color(42),
            $this->avatarPlaceholder->color(42),
        );
    }

    public function testColorWrapsAroundThePalette(): void
    {
        $paletteSize = 9;
        $this->assertSame(
            $this->avatarPlaceholder->color(1),
            $this->avatarPlaceholder->color(1 + $paletteSize),
        );
    }
}

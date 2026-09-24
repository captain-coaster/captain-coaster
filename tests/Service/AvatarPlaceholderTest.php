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

    public function testSlotIsDeterministicForSameSeed(): void
    {
        $this->assertSame($this->avatarPlaceholder->slot(42), $this->avatarPlaceholder->slot(42));
    }

    public function testSlotWrapsAroundWithinOneToSlotCount(): void
    {
        $this->assertSame(1, $this->avatarPlaceholder->slot(0));
        $this->assertSame(AvatarPlaceholder::SLOT_COUNT, $this->avatarPlaceholder->slot(AvatarPlaceholder::SLOT_COUNT - 1));
        $this->assertSame($this->avatarPlaceholder->slot(1), $this->avatarPlaceholder->slot(1 + AvatarPlaceholder::SLOT_COUNT));
    }

    /** Each slot needs its token, and a literal class in the component so Tailwind generates it. */
    public function testEverySlotHasATokenAndAComponentClass(): void
    {
        $root = \dirname(__DIR__, 2);
        $tokens = (string) file_get_contents($root.'/assets/styles/tokens.css');
        $component = (string) file_get_contents($root.'/templates/components/Avatar.html.twig');

        for ($slot = 1; $slot <= AvatarPlaceholder::SLOT_COUNT; ++$slot) {
            $this->assertStringContainsString('--avatar-'.$slot.':', $tokens);
            $this->assertStringContainsString('bg-[var(--avatar-'.$slot.')]', $component);
        }
    }
}

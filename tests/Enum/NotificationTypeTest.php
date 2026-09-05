<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\NotificationType;
use PHPUnit\Framework\TestCase;

class NotificationTypeTest extends TestCase
{
    public function testRankingRoutesToTheRankingPage(): void
    {
        $this->assertSame('ranking_index', NotificationType::Ranking->route());
    }

    public function testBadgeRoutesToTheProfilePage(): void
    {
        $this->assertSame('profile', NotificationType::Badge->route());
    }

    public function testRankingDoesNotEmailByDefault(): void
    {
        $this->assertFalse(NotificationType::Ranking->emailByDefault());
    }

    public function testBadgeEmailsByDefault(): void
    {
        $this->assertTrue(NotificationType::Badge->emailByDefault());
    }

    public function testOnlyBadgeParameterIsATranslationKey(): void
    {
        $this->assertTrue(NotificationType::Badge->parameterIsTranslationKey());
        $this->assertFalse(NotificationType::Ranking->parameterIsTranslationKey());
    }
}

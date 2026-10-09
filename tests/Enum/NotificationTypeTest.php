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

    public function testAnnouncementRoutesToProfileSettings(): void
    {
        $this->assertSame('profile_settings', NotificationType::Announcement->route());
    }
}

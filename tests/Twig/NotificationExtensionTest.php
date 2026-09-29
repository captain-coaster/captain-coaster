<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\User;
use App\Repository\NotificationRecipientRepository;
use App\Twig\NotificationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class NotificationExtensionTest extends TestCase
{
    public function testCountsOncePerRequest(): void
    {
        $repository = $this->createMock(NotificationRecipientRepository::class);
        $repository->expects($this->exactly(2))->method('countUnreadForUser')->willReturn(3, 5);
        $extension = new NotificationExtension($repository, $this->security(new User()));

        $this->assertSame(3, $extension->unreadNotificationCount());
        $this->assertSame(3, $extension->unreadNotificationCount());

        $extension->reset();
        $this->assertSame(5, $extension->unreadNotificationCount());
    }

    public function testAnonymousVisitorHasNoCount(): void
    {
        $repository = $this->createMock(NotificationRecipientRepository::class);
        $repository->expects($this->never())->method('countUnreadForUser');

        $this->assertSame(0, new NotificationExtension($repository, $this->security(null))->unreadNotificationCount());
    }

    private function security(?User $user): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return $security;
    }
}

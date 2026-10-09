<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserCheckerTest extends TestCase
{
    public function testEnabledAccountSignsIn(): void
    {
        $user = new User();
        $user->setEnabled(true);

        new UserChecker(new NullLogger())->checkPreAuth($user);

        $this->expectNotToPerformAssertions();
    }

    public function testDeletedAccountIsRefusedWithItsOwnMessage(): void
    {
        $user = $this->disabledUser();
        $user->setDeletedAt(new \DateTime());

        $this->assertSame('login.account_deleted', $this->refusal($user));
    }

    public function testBannedAccountIsRefusedWithItsOwnMessage(): void
    {
        $user = $this->disabledUser();
        $user->setBannedAt(new \DateTime());

        $this->assertSame('login.account_banned', $this->refusal($user));
    }

    public function testAnotherKindOfUserIsNotChecked(): void
    {
        new UserChecker(new NullLogger())->checkPreAuth(new InMemoryUser('api', null));

        $this->expectNotToPerformAssertions();
    }

    private function disabledUser(): User
    {
        $user = new User();
        $user->setEmail('rider@example.com');
        $user->setEnabled(false);

        return $user;
    }

    private function refusal(User $user): string
    {
        try {
            new UserChecker(new NullLogger())->checkPreAuth($user);
        } catch (CustomUserMessageAccountStatusException $exception) {
            return $exception->getMessageKey();
        }

        $this->fail('The account signed in.');
    }
}

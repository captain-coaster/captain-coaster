<?php

declare(strict_types=1);

namespace App\Tests\Notifier;

use App\Entity\User;
use App\Notifier\RequestContextNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

class RequestContextNotifierTest extends TestCase
{
    public function testAddsMethodPathAndMemberWithoutTheQueryString(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(42);

        $subject = $this->sentSubject(Request::create('/api?hash=secret'), $user);

        $this->assertSame("Access Denied.\nGET /api · member #42", $subject);
    }

    public function testLeavesTheMemberOutForAVisitor(): void
    {
        $this->assertSame("Access Denied.\nPOST /en/contact", $this->sentSubject(Request::create('/en/contact', 'POST'), null));
    }

    public function testLeavesTheSubjectAloneOutsideARequest(): void
    {
        $this->assertSame('Access Denied.', $this->sentSubject(null, null));
    }

    private function sentSubject(?Request $request, ?User $user): string
    {
        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $sent = null;
        $inner = $this->createMock(NotifierInterface::class);
        $inner->expects($this->once())->method('send')->willReturnCallback(static function (Notification $notification) use (&$sent): void {
            $sent = $notification->getSubject();
        });

        new RequestContextNotifier($inner, $requestStack, $security)->send(new Notification('Access Denied.'));

        return $sent;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\UnitsCookieSubscriber;
use App\Entity\User;
use App\Service\UnitsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class UnitsCookieSubscriberTest extends TestCase
{
    private Security&MockObject $security;
    private EntityManagerInterface&MockObject $em;
    private UnitsCookieSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->subscriber = new UnitsCookieSubscriber($this->security, $this->em);
    }

    private function respond(Request $request): Response
    {
        $kernel = $this->createMock(KernelInterface::class);
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        return $response;
    }

    public function testSetsUnitsCookieForAnonymousVisitor(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = Request::create('/en/map/?setUnits=imperial');

        $response = $this->respond($request);

        $cookie = $response->headers->getCookies()[0] ?? null;
        $this->assertNotNull($cookie);
        $this->assertSame(UnitsService::COOKIE_NAME, $cookie->getName());
        $this->assertSame('imperial', $cookie->getValue());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testWritesToProfileWhenLoggedIn(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getPreferredUnits')->willReturn('metric');
        $user->expects($this->once())->method('setPreferredUnits')->with('imperial');
        $this->em->expects($this->once())->method('flush');
        $this->security->method('getUser')->willReturn($user);

        $request = Request::create('/en/map/?setUnits=imperial');

        $response = $this->respond($request);

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testDoesNothingWhenSetUnitsIsMissing(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = Request::create('/en/map/');

        $response = $this->respond($request);

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testDoesNothingWhenSetUnitsValueIsInvalid(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = Request::create('/en/map/?setUnits=furlongs');

        $response = $this->respond($request);

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testDoesNothingOnSubRequests(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = Request::create('/en/map/?setUnits=imperial');
        $kernel = $this->createMock(KernelInterface::class);
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertCount(0, $response->headers->getCookies());
    }
}

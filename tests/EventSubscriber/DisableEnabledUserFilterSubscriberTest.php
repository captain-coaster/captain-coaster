<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Doctrine\EnabledUserFilter;
use App\EventSubscriber\DisableEnabledUserFilterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class DisableEnabledUserFilterSubscriberTest extends TestCase
{
    /** @return iterable<string, array{?string, int, bool}> */
    public static function requests(): iterable
    {
        yield 'dashboard' => ['admin', HttpKernelInterface::MAIN_REQUEST, true];
        yield 'admin page' => ['admin_moderation_queue', HttpKernelInterface::MAIN_REQUEST, true];
        yield 'public page' => ['coaster_show', HttpKernelInterface::MAIN_REQUEST, false];
        yield 'look-alike' => ['administrator', HttpKernelInterface::MAIN_REQUEST, false];
        yield 'no route' => [null, HttpKernelInterface::MAIN_REQUEST, false];
        yield 'sub-request' => ['admin', HttpKernelInterface::SUB_REQUEST, false];
    }

    #[DataProvider('requests')]
    public function testDisablesTheFilterOnAdminRoutesOnly(?string $route, int $type, bool $disabled): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(true);
        $filters->expects($disabled ? $this->once() : $this->never())->method('disable')->with(EnabledUserFilter::NAME);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $request = new Request();
        $request->attributes->set('_route', $route);
        new DisableEnabledUserFilterSubscriber($em)->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type));
    }

    public function testLeavesAnAlreadyDisabledFilterAlone(): void
    {
        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);
        $filters->expects($this->never())->method('disable');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getFilters')->willReturn($filters);

        $request = new Request();
        $request->attributes->set('_route', 'admin');
        new DisableEnabledUserFilterSubscriber($em)->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));
    }
}

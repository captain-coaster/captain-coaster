<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LocaleCookieSubscriber;
use App\Service\LocalePreferenceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class LocaleCookieSubscriberTest extends TestCase
{
    private LocaleCookieSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new LocaleCookieSubscriber(new LocalePreferenceService(['en', 'fr', 'es', 'de']));
    }

    private function respond(Request $request): Response
    {
        $kernel = $this->createMock(KernelInterface::class);
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        return $response;
    }

    public function testSetsLocaleCookieWhenSetLocaleIsSupported(): void
    {
        $request = Request::create('/fr/map/?setLocale=fr');

        $response = $this->respond($request);

        $cookie = $response->headers->getCookies()[0] ?? null;
        $this->assertNotNull($cookie);
        $this->assertSame(LocalePreferenceService::COOKIE_NAME, $cookie->getName());
        $this->assertSame('fr', $cookie->getValue());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testDoesNothingWhenSetLocaleIsMissing(): void
    {
        $request = Request::create('/en/map/');

        $response = $this->respond($request);

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testDoesNothingWhenSetLocaleIsNotSupported(): void
    {
        $request = Request::create('/en/map/?setLocale=xx');

        $response = $this->respond($request);

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testDoesNothingOnSubRequests(): void
    {
        $request = Request::create('/fr/map/?setLocale=fr');
        $kernel = $this->createMock(KernelInterface::class);
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertCount(0, $response->headers->getCookies());
    }
}

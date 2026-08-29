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
        $this->subscriber = new LocaleCookieSubscriber();
    }

    private function respond(Request $request): Response
    {
        $kernel = $this->createMock(KernelInterface::class);
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        return $response;
    }

    public function testSetsLocaleCookieToTheRequestsResolvedLocaleWhenSetLocaleIsPresent(): void
    {
        $request = Request::create('/fr/map/?setLocale=1');
        $request->setLocale('fr');

        $response = $this->respond($request);

        $cookie = $response->headers->getCookies()[0] ?? null;
        $this->assertNotNull($cookie);
        $this->assertSame(LocalePreferenceService::COOKIE_NAME, $cookie->getName());
        $this->assertSame('fr', $cookie->getValue());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function testSetsLocaleCookieRegardlessOfSetLocaleValue(): void
    {
        // setLocale is a presence flag, not a value -- any value (or an
        // empty one) triggers the same behavior as long as the param
        // exists. The cookie value always comes from the request's own
        // resolved locale, never from the query string.
        $request = Request::create('/de/map/?setLocale=whatever');
        $request->setLocale('de');

        $response = $this->respond($request);

        $cookie = $response->headers->getCookies()[0] ?? null;
        $this->assertNotNull($cookie);
        $this->assertSame('de', $cookie->getValue());
    }

    public function testDoesNothingWhenSetLocaleIsMissing(): void
    {
        $request = Request::create('/en/map/');
        $request->setLocale('en');

        $response = $this->respond($request);

        $this->assertCount(0, $response->headers->getCookies());
    }

    public function testDoesNothingOnSubRequests(): void
    {
        $request = Request::create('/fr/map/?setLocale=1');
        $request->setLocale('fr');
        $kernel = $this->createMock(KernelInterface::class);
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertCount(0, $response->headers->getCookies());
    }
}

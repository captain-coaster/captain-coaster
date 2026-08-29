<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LocalePreferenceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class LocalePreferenceServiceTest extends TestCase
{
    private LocalePreferenceService $service;

    protected function setUp(): void
    {
        $this->service = new LocalePreferenceService(['en', 'fr', 'es', 'de']);
    }

    public function testResolveAnonymousLocaleUsesValidCookieOverBrowserLanguage(): void
    {
        $request = Request::create('/');
        $request->cookies->set(LocalePreferenceService::COOKIE_NAME, 'fr');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $this->assertSame('fr', $this->service->resolveAnonymousLocale($request));
    }

    public function testResolveAnonymousLocaleIgnoresInvalidCookieValue(): void
    {
        $request = Request::create('/');
        $request->cookies->set(LocalePreferenceService::COOKIE_NAME, 'xx-not-a-real-locale');
        $request->headers->set('Accept-Language', 'es-ES,es;q=0.9');

        $this->assertSame('es', $this->service->resolveAnonymousLocale($request));
    }

    public function testResolveAnonymousLocaleFallsBackToAcceptLanguageWithNoCookie(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9');

        $this->assertSame('de', $this->service->resolveAnonymousLocale($request));
    }

    public function testIsSupportedLocaleAcceptsConfiguredLocales(): void
    {
        $this->assertTrue($this->service->isSupportedLocale('en'));
        $this->assertTrue($this->service->isSupportedLocale('fr'));
    }

    public function testIsSupportedLocaleRejectsUnknownValue(): void
    {
        $this->assertFalse($this->service->isSupportedLocale('xx'));
        $this->assertFalse($this->service->isSupportedLocale(''));
    }
}

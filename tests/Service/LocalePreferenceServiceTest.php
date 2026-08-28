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

    public function testIsSafeRedirectPathAcceptsLocalePrefixedRelativePath(): void
    {
        $this->assertTrue($this->service->isSafeRedirectPath('/fr/coasters/123'));
        $this->assertTrue($this->service->isSafeRedirectPath('/en/map/'));
    }

    public function testIsSafeRedirectPathRejectsAbsoluteUrl(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath('https://evil.example.com/phishing'));
    }

    public function testIsSafeRedirectPathRejectsProtocolRelativeUrl(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath('//evil.example.com/phishing'));
    }

    public function testIsSafeRedirectPathRejectsPathWithoutLocalePrefix(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath('/some/random/path'));
    }

    public function testIsSafeRedirectPathRejectsNullOrEmpty(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath(null));
        $this->assertFalse($this->service->isSafeRedirectPath(''));
    }
}

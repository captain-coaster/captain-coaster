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

    public function testIsSafeRedirectPathAcceptsPathMatchingTheGivenLocale(): void
    {
        $this->assertTrue($this->service->isSafeRedirectPath('/fr/coasters/123', 'fr'));
        $this->assertTrue($this->service->isSafeRedirectPath('/en/map/', 'en'));
        $this->assertTrue($this->service->isSafeRedirectPath('/en', 'en'));
        $this->assertTrue($this->service->isSafeRedirectPath('/en?foo=bar', 'en'));
    }

    public function testIsSafeRedirectPathRejectsPathForADifferentLocaleThanRequested(): void
    {
        // The redirect's own locale segment must match the locale being
        // switched to -- a /fr/... path is never a safe target when
        // switching to "de", even though /fr/... is a valid path in
        // isolation.
        $this->assertFalse($this->service->isSafeRedirectPath('/fr/coasters/123', 'de'));
        $this->assertFalse($this->service->isSafeRedirectPath('/end-of-something', 'en'));
    }

    public function testIsSafeRedirectPathRejectsAbsoluteUrl(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath('https://evil.example.com/phishing', 'en'));
    }

    public function testIsSafeRedirectPathRejectsProtocolRelativeUrl(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath('//evil.example.com/phishing', 'en'));
    }

    public function testIsSafeRedirectPathRejectsPathWithoutLocalePrefix(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath('/some/random/path', 'en'));
    }

    public function testIsSafeRedirectPathRejectsNullOrEmpty(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath(null, 'en'));
        $this->assertFalse($this->service->isSafeRedirectPath('', 'en'));
    }

    public function testIsSafeRedirectPathRejectsDirectoryTraversal(): void
    {
        $this->assertFalse($this->service->isSafeRedirectPath('/en/../../etc', 'en'));
        $this->assertFalse($this->service->isSafeRedirectPath('/fr/../../../admin', 'fr'));
        $this->assertFalse($this->service->isSafeRedirectPath('/../en/coasters', 'en'));
        $this->assertFalse($this->service->isSafeRedirectPath('/de/path/..', 'de'));
    }
}

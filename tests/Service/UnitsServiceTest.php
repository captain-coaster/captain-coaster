<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UnitsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class UnitsServiceTest extends TestCase
{
    private Security&MockObject $security;
    private RequestStack $requestStack;
    private UnitsService $service;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->requestStack = new RequestStack();
        $this->service = new UnitsService($this->security, $this->requestStack);
    }

    private function pushRequestWithAcceptLanguage(string $acceptLanguage): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', $acceptLanguage);
        $this->requestStack->push($request);
    }

    public function testMetersToFeet(): void
    {
        $this->assertSame(66, $this->service->metersToFeet(20));
        $this->assertSame(0, $this->service->metersToFeet(0));
    }

    public function testKphToMph(): void
    {
        $this->assertSame(62, $this->service->kphToMph(100));
    }

    public function testKmToMiles(): void
    {
        $this->assertSame(6, $this->service->kmToMiles(10));
    }

    public function testIsImperialForLoggedInUserUsesProfilePreferenceRegardlessOfBrowserLanguage(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getPreferredUnits')->willReturn('imperial');
        $this->security->method('getUser')->willReturn($user);

        $this->pushRequestWithAcceptLanguage('fr-FR,fr;q=0.9');

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithUsRegionGuessesImperial(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en-US,en;q=0.9');

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithGbRegionGuessesImperial(): void
    {
        // The UK is officially metric but legally imperial for road
        // distances and speeds (miles, mph) -- exactly the units this
        // service converts -- so en-GB is treated as imperial like en-US.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en-GB,en;q=0.9');

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithCaRegionDefaultsMetric(): void
    {
        // Canada uses km/h for road speed limits, unlike the UK.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en-CA,en;q=0.9');

        $this->assertFalse($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithAuRegionDefaultsMetric(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en-AU,en;q=0.9');

        $this->assertFalse($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithBareEnglishNoRegionDefaultsMetric(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en;q=0.9');

        $this->assertFalse($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithBareEnglishThenUsRegionGuessesImperial(): void
    {
        // A bare "en" alone is ambiguous and gets skipped; the next
        // entry that carries a region (en-US) decides.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en,en-US;q=0.9,fr;q=0.8,fr-FR;q=0.7');

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithBareEnglishThenGbRegionGuessesImperial(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en,en-GB;q=0.9');

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithBareEnglishThenCaRegionDefaultsMetric(): void
    {
        // The first region found (en-CA) is not an imperial one --
        // still metric.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en,en-CA;q=0.9');

        $this->assertFalse($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithBareEnglishThenNonEnglishDefaultsMetric(): void
    {
        // Nothing in second position to disambiguate with.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en,fr;q=0.9');

        $this->assertFalse($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserSkipsBareNonEnglishTopToFindUsRegion(): void
    {
        // A bare "fr" (no region) is just as uninformative as a bare
        // "en" -- it's skipped, and the first region found anywhere in
        // the list decides, regardless of which language it belongs to.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('fr,en-US;q=0.5');

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserStopsAtFirstRegionEvenIfALaterOneIsImperial(): void
    {
        // The first region-qualified entry decides and the scan stops
        // there -- a decisive metric region (fr-FR) is never overridden
        // by an imperial region further down the list.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('fr-FR;q=0.9,en-US;q=0.5');

        $this->assertFalse($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserWithNoRequestDefaultsMetric(): void
    {
        $this->security->method('getUser')->willReturn(null);
        // No request pushed onto the stack at all.

        $this->assertFalse($this->service->isImperial());
    }

    public function testMetersOrFeetFormatsWithSuffixForMetric(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('fr-FR,fr;q=0.9');

        $this->assertSame('20 m', $this->service->metersOrFeet(20));
    }

    public function testMetersOrFeetFormatsWithSuffixForImperial(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en-US,en;q=0.9');

        $this->assertSame('66 ft', $this->service->metersOrFeet(20));
    }

    public function testKphOrMphFormatsWithSuffix(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en-US,en;q=0.9');

        $this->assertSame('62 mph', $this->service->kphOrMph(100));
    }

    public function testKmOrMiFormatsWithSuffix(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('en-US,en;q=0.9');

        $this->assertSame('6 mi', $this->service->kmOrMi(10));
    }

    public function testGuessUnitsFromRequestUsesCfIpCountryUs(): void
    {
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'US');

        $this->assertSame('imperial', $this->service->guessUnitsFromRequest($request));
    }

    public function testGuessUnitsFromRequestUsesCfIpCountryGb(): void
    {
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'GB');

        $this->assertSame('imperial', $this->service->guessUnitsFromRequest($request));
    }

    public function testGuessUnitsFromRequestCfIpCountryMetricElsewhere(): void
    {
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'FR');

        $this->assertSame('metric', $this->service->guessUnitsFromRequest($request));
    }

    public function testGuessUnitsFromRequestFallsBackToAcceptLanguageWhenCfHeaderAbsent(): void
    {
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $this->assertSame('imperial', $this->service->guessUnitsFromRequest($request));
    }

    public function testGuessUnitsFromRequestCfIpCountryTakesPriorityOverAcceptLanguage(): void
    {
        // CF-IPCountry says France (metric); Accept-Language alone would
        // have said US (imperial) -- the real-country signal wins.
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'FR');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $this->assertSame('metric', $this->service->guessUnitsFromRequest($request));
    }

    public function testIsImperialForAnonymousUserUsesCookieOverAcceptLanguage(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'fr-FR,fr;q=0.9');
        $request->cookies->set(UnitsService::COOKIE_NAME, 'imperial');
        $this->requestStack->push($request);

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialForAnonymousUserIgnoresInvalidCookieValue(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');
        $request->cookies->set(UnitsService::COOKIE_NAME, 'furlongs');
        $this->requestStack->push($request);

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialAppliesSetUnitsQueryParamImmediatelyRegardlessOfUserOrCookie(): void
    {
        // The very request carrying ?setUnits=imperial must already reflect
        // it, before UnitsCookieSubscriber (KernelEvents::RESPONSE) has had
        // a chance to persist it for future requests -- otherwise the page
        // the visitor lands on after clicking the switcher still renders
        // with the old units.
        $user = $this->createMock(User::class);
        $user->method('getPreferredUnits')->willReturn('metric');
        $this->security->method('getUser')->willReturn($user);

        $request = Request::create('/?setUnits=imperial');
        $request->cookies->set(UnitsService::COOKIE_NAME, 'metric');
        $this->requestStack->push($request);

        $this->assertTrue($this->service->isImperial());
    }

    public function testIsImperialIgnoresInvalidSetUnitsQueryParam(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getPreferredUnits')->willReturn('imperial');
        $this->security->method('getUser')->willReturn($user);

        $request = Request::create('/?setUnits=furlongs');
        $this->requestStack->push($request);

        $this->assertTrue($this->service->isImperial());
    }

    public function testGuessUnitsFromRequestTreatsCfIpCountryXxAsAbsentAndFallsBackToAcceptLanguage(): void
    {
        // Cloudflare sends 'XX' when the visitor's country is unknown --
        // not a real country, so it shouldn't override a legitimate
        // Accept-Language signal by forcing metric.
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'XX');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $this->assertSame('imperial', $this->service->guessUnitsFromRequest($request));
    }

    public function testGuessUnitsFromRequestTreatsCfIpCountryT1AsAbsentAndFallsBackToAcceptLanguage(): void
    {
        // Cloudflare sends 'T1' for Tor exit nodes -- also not a real
        // country.
        $request = Request::create('/');
        $request->headers->set('CF-IPCountry', 'T1');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $this->assertSame('imperial', $this->service->guessUnitsFromRequest($request));
    }
}

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
        $user->method('isImperial')->willReturn(true);
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
        // A bare "en" alone is ambiguous; the next-preferred language
        // (en-US) disambiguates it.
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
        // The disambiguating second choice is region-qualified but not
        // an imperial region -- still metric.
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

    public function testIsImperialForAnonymousUserWithNonEnglishTopIgnoresLowerPriorityUsRegion(): void
    {
        // English-US buried behind a non-English top choice is not a
        // signal -- only a bare "en" in top position triggers the
        // second-choice fallback.
        $this->security->method('getUser')->willReturn(null);
        $this->pushRequestWithAcceptLanguage('fr,en-US;q=0.5');

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
}

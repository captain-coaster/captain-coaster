<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\ReviewLanguagePreferenceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

class ReviewLanguagePreferenceServiceTest extends TestCase
{
    private Security&MockObject $security;
    private ReviewLanguagePreferenceService $service;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->service = new ReviewLanguagePreferenceService($this->security);
    }

    public function testAnonymousVisitorGetsCurrentPageLocaleOnly(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = Request::create('/de/coasters/1');
        $request->setLocale('de');

        $this->assertSame(['de'], $this->service->resolve($request));
    }

    public function testLoggedInUserWithNoExplicitPreferenceGetsOwnLocaleOnly(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getPreferredReviewLanguages')->willReturn([]);
        $user->method('getPreferredLocale')->willReturn('de');
        $this->security->method('getUser')->willReturn($user);

        $request = Request::create('/en/coasters/1');
        $request->setLocale('en');

        $this->assertSame(['de'], $this->service->resolve($request));
    }

    public function testLoggedInUserWithExplicitPreferenceGetsThatList(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getPreferredReviewLanguages')->willReturn(['de', 'en']);
        $this->security->method('getUser')->willReturn($user);

        $request = Request::create('/en/coasters/1');
        $request->setLocale('en');

        $this->assertSame(['de', 'en'], $this->service->resolve($request));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Notifier\CustomLoginLinkNotification;
use App\Repository\UserRepository;
use App\Service\EmailValidationService;
use App\Service\LoginLinkService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginLinkServiceTest extends TestCase
{
    private RateLimiterFactoryInterface&MockObject $ipLimiterFactory;
    private RateLimiterFactoryInterface&MockObject $emailLimiterFactory;
    private EmailValidationService&MockObject $emailValidator;
    private UserRepository&MockObject $userRepository;
    private LoginLinkHandlerInterface&MockObject $loginLinkHandler;
    private NotifierInterface&MockObject $notifier;
    private TranslatorInterface&MockObject $translator;
    private LoginLinkService $service;

    protected function setUp(): void
    {
        $this->ipLimiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->emailLimiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->emailValidator = $this->createMock(EmailValidationService::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->loginLinkHandler = $this->createMock(LoginLinkHandlerInterface::class);
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->service = new LoginLinkService(
            $this->ipLimiterFactory,
            $this->emailLimiterFactory,
            $this->emailValidator,
            $this->userRepository,
            $this->loginLinkHandler,
            $this->notifier,
            $this->translator
        );
    }

    public function testRequestLoginLinkReturnsTrueWhenIpRateLimitExceeded(): void
    {
        $this->stubLimiters(ipAccepted: false, emailAccepted: true);

        $this->notifier->expects($this->never())->method('send');

        $this->assertTrue($this->service->requestLoginLink('user@example.com', '1.2.3.4'));
    }

    public function testRequestLoginLinkReturnsTrueWhenEmailRateLimitExceeded(): void
    {
        $this->stubLimiters(ipAccepted: true, emailAccepted: false);

        $this->notifier->expects($this->never())->method('send');

        $this->assertTrue($this->service->requestLoginLink('user@example.com', '1.2.3.4'));
    }

    public function testRequestLoginLinkSendsNotificationForEnabledUser(): void
    {
        $this->stubLimiters(ipAccepted: true, emailAccepted: true);

        $user = $this->createUser('user@example.com');

        $this->emailValidator->method('isValidEmail')->with('user@example.com')->willReturn(true);
        $this->userRepository->method('findOneBy')->with(['email' => 'user@example.com'])->willReturn($user);
        $this->translator->method('trans')->with('login.email.title')->willReturn('Sign in to Captain Coaster');
        $this->loginLinkHandler->method('createLoginLink')->with($user)
            ->willReturn(new LoginLinkDetails('https://example.com/login/check?x', new \DateTimeImmutable('+15 minutes')));

        $this->notifier->expects($this->once())
            ->method('send')
            ->with(
                $this->isInstanceOf(CustomLoginLinkNotification::class),
                $this->callback(static fn (Recipient $recipient): bool => 'user@example.com' === $recipient->getEmail())
            );

        $this->assertFalse($this->service->requestLoginLink('user@example.com', '1.2.3.4'));
    }

    public function testRequestLoginLinkDoesNotSendForDisabledUser(): void
    {
        $this->stubLimiters(ipAccepted: true, emailAccepted: true);

        $user = $this->createUser('user@example.com', enabled: false);

        $this->emailValidator->method('isValidEmail')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn($user);

        $this->notifier->expects($this->never())->method('send');

        $this->assertFalse($this->service->requestLoginLink('user@example.com', '1.2.3.4'));
    }

    public function testRequestLoginLinkDoesNotSendForUnknownEmail(): void
    {
        $this->stubLimiters(ipAccepted: true, emailAccepted: true);

        $this->emailValidator->method('isValidEmail')->willReturn(true);
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->notifier->expects($this->never())->method('send');

        $this->assertFalse($this->service->requestLoginLink('unknown@example.com', '1.2.3.4'));
    }

    public function testRequestLoginLinkDoesNotSendForInvalidEmailFormat(): void
    {
        $this->stubLimiters(ipAccepted: true, emailAccepted: true);

        $this->emailValidator->method('isValidEmail')->willReturn(false);
        $this->userRepository->expects($this->never())->method('findOneBy');
        $this->notifier->expects($this->never())->method('send');

        $this->assertFalse($this->service->requestLoginLink('not-an-email', '1.2.3.4'));
    }

    public function testRequestLoginLinkNormalizesEmailCaseForRateLimiterKey(): void
    {
        $ipLimiter = $this->createMock(LimiterInterface::class);
        $ipLimiter->method('consume')->willReturn(new RateLimit(2, new \DateTimeImmutable(), true, 3));
        $this->ipLimiterFactory->method('create')->willReturn($ipLimiter);

        $emailLimiter = $this->createMock(LimiterInterface::class);
        $emailLimiter->method('consume')->willReturn(new RateLimit(2, new \DateTimeImmutable(), true, 3));

        $this->emailLimiterFactory->expects($this->once())
            ->method('create')
            ->with('user@example.com')
            ->willReturn($emailLimiter);

        $this->emailValidator->method('isValidEmail')->willReturn(false);

        $this->service->requestLoginLink('User@Example.com', '1.2.3.4');
    }

    private function stubLimiters(bool $ipAccepted, bool $emailAccepted): void
    {
        $ipLimiter = $this->createMock(LimiterInterface::class);
        $ipLimiter->method('consume')->willReturn(new RateLimit(0, new \DateTimeImmutable(), $ipAccepted, 3));
        $this->ipLimiterFactory->method('create')->willReturn($ipLimiter);

        $emailLimiter = $this->createMock(LimiterInterface::class);
        $emailLimiter->method('consume')->willReturn(new RateLimit(0, new \DateTimeImmutable(), $emailAccepted, 3));
        $this->emailLimiterFactory->method('create')->willReturn($emailLimiter);
    }

    private function createUser(string $email, bool $enabled = true): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setEnabled($enabled);

        return $user;
    }
}

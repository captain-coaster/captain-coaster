<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\GoogleAuthenticator;
use App\Service\ProfilePictureManager;
use App\Service\UnitsService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class GoogleAuthenticatorTest extends TestCase
{
    private ClientRegistry&MockObject $clientRegistry;
    private EntityManagerInterface&MockObject $em;
    private ProfilePictureManager&MockObject $profilePictureManager;
    private RouterInterface&MockObject $router;
    private LoggerInterface&MockObject $logger;
    private UnitsService&MockObject $unitsService;
    private GoogleAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->clientRegistry = $this->createMock(ClientRegistry::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->profilePictureManager = $this->createMock(ProfilePictureManager::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->unitsService = $this->createMock(UnitsService::class);

        $this->authenticator = new GoogleAuthenticator(
            $this->clientRegistry,
            $this->em,
            $this->profilePictureManager,
            $this->router,
            $this->logger,
            $this->unitsService
        );
    }

    private function requestWithSession(): Request
    {
        $request = Request::create('/connect/google/check');
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    public function testOnAuthenticationSuccessRedirectsToSavedTargetPathWhenPresent(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()->set('_security.main.target_path', '/fr/coasters/123');

        $token = $this->createMock(TokenInterface::class);

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/fr/coasters/123', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessFallsBackToLocaleAtLoginWhenNoTargetPath(): void
    {
        $request = $this->requestWithSession();
        $request->getSession()->set('locale_at_login', 'fr');

        $token = $this->createMock(TokenInterface::class);

        $this->router->expects($this->once())
            ->method('generate')
            ->with('default_index', ['_locale' => 'fr'])
            ->willReturn('/fr/');

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/fr/', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessFallsBackToRequestLocaleWhenSessionHasNoLocaleAtLogin(): void
    {
        $request = $this->requestWithSession();
        // locale_at_login intentionally not set in session.

        $token = $this->createMock(TokenInterface::class);

        $this->router->expects($this->once())
            ->method('generate')
            ->with('default_index', ['_locale' => $request->getLocale()])
            ->willReturn('/en/');

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testFindOrCreateUserInitializesPreferredUnitsFromRequest(): void
    {
        $googleUser = $this->createMock(\League\OAuth2\Client\Provider\GoogleUser::class);
        $googleUser->method('getId')->willReturn('google-123');
        $googleUser->method('getEmail')->willReturn('new@example.com');

        $this->em->method('getRepository')->willReturn($this->createConfiguredMock(
            \Doctrine\ORM\EntityRepository::class,
            ['findOneBy' => null]
        ));

        $this->unitsService->expects($this->once())
            ->method('guessUnitsFromRequest')
            ->willReturn('imperial');

        $request = $this->requestWithSession();

        $reflection = new \ReflectionMethod($this->authenticator, 'findOrCreateUser');
        $user = $reflection->invoke($this->authenticator, $googleUser, $request);

        $this->assertSame('imperial', $user->getPreferredUnits());
    }
}

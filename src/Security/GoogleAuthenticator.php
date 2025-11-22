<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\ProfilePictureManager;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Authenticator for Google login.
 */
class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly ProfilePictureManager $profilePictureManager,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_google_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $request) {
            /** @var GoogleUser $googleUser */
            $googleUser = $client->fetchUserFromToken($accessToken);

            // 1) try to find a user based on its Google ID or email, otherwise create new User
            $user = $this->findOrCreateUser($googleUser, $request);

            // 2) update user details based on token
            $this->updateUserDetails($user, $googleUser, $request);

            return $user;
        }), [
            new RememberMeBadge(),
        ]);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->router->generate('default_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     * This redirects to the 'login'.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('login'), Response::HTTP_TEMPORARY_REDIRECT);
    }

    /** Try to find user using first google id then email, otherwise create new User */
    private function findOrCreateUser(GoogleUser $googleUser, Request $request)
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => $googleUser->getId()])
            ?? $this->em->getRepository(User::class)->findOneBy(['email' => $googleUser->getEmail()]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setPreferredLocale($request->getSession()->get('locale_at_login', 'en'));
            $user->setEnabled(true);
            $user->setVerified(true);
        }

        return $user;
    }

    private function updateUserDetails(User $user, GoogleUser $googleUser, Request $request): void
    {
        try {
            // update user fields based on token
            $user->setGoogleId($googleUser->getId());
            $user->setEmail($googleUser->getEmail());

            // Only set first name and last name if they're not already set
            if (!$user->getFirstName()) {
                $user->setFirstName($googleUser->getFirstName());
            }

            if (!$user->getLastName()) {
                $user->setLastName($googleUser->getLastName());
            }

            $this->em->persist($user);
            $this->em->flush();

            // Download and store the profile picture if available and not yet set
            if ($googleUser->getAvatar() && !$user->getProfilePicture()) {
                $filename = $this->profilePictureManager->uploadProfilePictureFromUrl($googleUser->getAvatar(), $user);
                if ($filename) {
                    $user->setProfilePicture($filename);
                    $this->em->persist($user);
                    $this->em->flush();
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error while updating user details: '.$e->getMessage());

            throw new AuthenticationException('Authentication error');
        }
    }
}

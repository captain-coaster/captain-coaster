<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\FacebookUser;
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

class FacebookAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_facebook_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('facebook');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
            /** @var FacebookUser $facebookUser */
            $facebookUser = $client->fetchUserFromToken($accessToken);

            // 1) try to find a user based on its Google ID or email, otherwise create new User
            $user = $this->findOrCreateUser($facebookUser);

            // 2) update user details based on token
            $this->updateUserDetails($user, $facebookUser);

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

        return new RedirectResponse($this->router->generate('bdd_index'));
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
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('login'), Response::HTTP_TEMPORARY_REDIRECT);
    }

    /** Try to find user using first google id then email, otherwise create new User */
    private function findOrCreateUser(FacebookUser $facebookUser)
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['facebookId' => $facebookUser->getId()])
            ?? $this->em->getRepository(User::class)->findOneBy(['email' => $facebookUser->getEmail()]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setPreferredLocale($facebookUser->getLocale());
            $user->setEnabled(true);
        }

        return $user;
    }

    private function updateUserDetails(User $user, FacebookUser $facebookUser): void
    {
        try {
            // update user fields based on token
            $user->setFacebookId($facebookUser->getId());
            if ($facebookUser->getEmail()) {
                $user->setEmail($facebookUser->getEmail());
            }
            $user->setFirstName($facebookUser->getFirstName());
            $user->setLastName($facebookUser->getLastName());
            $user->setProfilePicture($facebookUser->getPictureUrl());
            $user->setLastLogin(new \DateTime());

            // don't override displayName at every login
            if (!$user->getDisplayName()) {
                $user->setDisplayName($facebookUser->getFirstName().' '.$facebookUser->getLastName());
            }

            $this->em->persist($user);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->logger->error('Error while updating user details: '.$e->getMessage());

            throw new AuthenticationException('Authentication error');
        }
    }
}

<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class TokenAuthenticator extends AbstractGuardAuthenticator
{
    const HEADER = 'Authorization';
    const DEPRECATED_HEADER = 'X-AUTH-TOKEN';

    public function supports(Request $request): bool
    {
        return $request->headers->has(self::HEADER) || $request->headers->has(self::DEPRECATED_HEADER);
    }

    public function getCredentials(Request $request): array
    {
        if ($request->headers->has(self::HEADER)) {
            $token = $request->headers->get(self::HEADER);

            if (str_starts_with($token, 'Bearer ')) {
                $token = str_replace('Bearer ', '', $token);
            }

            return ['token' => $token];
        }


        return ['token' => $request->headers->get(self::DEPRECATED_HEADER)];
    }

    public function getUser($credentials, UserProviderInterface $userProvider): ?UserInterface
    {
        $apiKey = $credentials['token'];

        if (null === $apiKey) {
            return null;
        }

        // if a User object, checkCredentials() is called
        return $userProvider->loadUserByUsername($apiKey);
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        // apiKey found means OK
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(
            ['message' => strtr($exception->getMessageKey(), $exception->getMessageData())],
            Response::HTTP_FORBIDDEN
        );
    }

    public function start(Request $request, AuthenticationException $authException = null): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'Authentication Required'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    final public const string HEADER = 'Authorization';
    final public const string DEPRECATED_HEADER = 'X-AUTH-TOKEN';

    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::HEADER) || $request->headers->has(self::DEPRECATED_HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $this->extractToken($request);

        if (null === $apiToken) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        return new SelfValidatingPassport(
            new UserBadge($apiToken, fn (string $userIdentifier): ?UserInterface => $this->userRepository->findOneBy(['apiKey' => $userIdentifier]))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['message' => strtr($exception->getMessageKey(), $exception->getMessageData())],
            Response::HTTP_FORBIDDEN
        );
    }

    /** Extract api key from request */
    private function extractToken(Request $request): ?string
    {
        if ($request->headers->has(self::HEADER)) {
            $token = $request->headers->get(self::HEADER);

            if (str_starts_with($token, 'Bearer ')) {
                $token = str_replace('Bearer ', '', $token);
            }

            return $token;
        }

        return $request->headers->get(self::DEPRECATED_HEADER);
    }
}

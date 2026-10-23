<?php

declare(strict_types=1);

namespace App\Service\Moderation;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * A keyed, one-way fingerprint of an IP address, stored with a rating so moderation can tell that two accounts rated
 * from the same network without keeping the address itself. Changing the app secret changes every fingerprint.
 */
class IpHasher
{
    public function __construct(#[Autowire('%kernel.secret%')] private readonly string $secret)
    {
    }

    public function hash(?string $ip): ?string
    {
        if (null === $ip || false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            return null;
        }

        // An IPv6 address changes within its /64, which belongs to one connection
        return hash_hmac('sha256', IpUtils::anonymize($ip, 0, 8), $this->secret);
    }
}

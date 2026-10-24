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

        // An IPv4 address seen through IPv6 (::ffff:a.b.c.d, whatever the spelling) is the same address
        $packed = (string) inet_pton($ip);
        if (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $ip = (string) inet_ntop(substr($packed, 12));
        }

        // An IPv6 address changes within its /64, which belongs to one connection
        $network = IpUtils::anonymize($ip, 0, 8);

        // The prefix keeps the secret's other uses apart
        return hash_hmac('sha256', 'rating-ip|'.$network, $this->secret);
    }
}

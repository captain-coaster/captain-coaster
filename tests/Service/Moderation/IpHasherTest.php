<?php

declare(strict_types=1);

namespace App\Tests\Service\Moderation;

use App\Service\Moderation\IpHasher;
use PHPUnit\Framework\TestCase;

class IpHasherTest extends TestCase
{
    public function testSameAddressSameFingerprint(): void
    {
        $hasher = new IpHasher('secret');

        $this->assertSame($hasher->hash('203.0.113.7'), $hasher->hash('203.0.113.7'));
        $this->assertSame(64, \strlen((string) $hasher->hash('203.0.113.7')));
    }

    public function testEveryIpv4AddressCounts(): void
    {
        $hasher = new IpHasher('secret');

        $this->assertNotSame($hasher->hash('203.0.113.7'), $hasher->hash('203.0.113.8'));
    }

    public function testIpv6AddressesOfOneConnectionShareAFingerprint(): void
    {
        $hasher = new IpHasher('secret');

        $this->assertSame($hasher->hash('2001:db8:1:2::a'), $hasher->hash('2001:db8:1:2:ffff::b'));
        $this->assertNotSame($hasher->hash('2001:db8:1:2::a'), $hasher->hash('2001:db8:1:3::a'));
    }

    public function testIpv4MappedAddressIsTheIpv4Address(): void
    {
        $hasher = new IpHasher('secret');

        $this->assertSame($hasher->hash('203.0.113.7'), $hasher->hash('::ffff:203.0.113.7'));
    }

    public function testKeyedBySecret(): void
    {
        $this->assertNotSame(new IpHasher('one')->hash('203.0.113.7'), new IpHasher('two')->hash('203.0.113.7'));
        $this->assertNotSame(hash('sha256', '203.0.113.7'), new IpHasher('one')->hash('203.0.113.7'));
    }

    public function testNoAddressNoFingerprint(): void
    {
        $hasher = new IpHasher('secret');

        $this->assertNull($hasher->hash(null));
        $this->assertNull($hasher->hash('not an ip'));
    }
}

<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Park;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/** @extends PersistentProxyObjectFactory<Park> */
final class ParkFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Park::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement(['Europa-Park', 'Cedar Point', 'Six Flags Magic Mountain']),
            'country' => CountryFactory::random(),
            'enabled' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Manufacturer;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Manufacturer>
 */
final class ManufacturerFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Manufacturer::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement([
                'Bolliger & Mabillard',
                'Intamin',
                'Vekoma',
                'Rocky Mountain Construction',
                'Mack Rides',
                'Gerstlauer',
                'Zamperla',
                'S&S - Sansei Technologies',
                'Great Coasters International',
                'Premier Rides',
            ]),
        ];
    }
}

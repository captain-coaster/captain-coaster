<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Continent;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Continent>
 */
final class ContinentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Continent::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement(['Europe', 'North America']),
        ];
    }
}

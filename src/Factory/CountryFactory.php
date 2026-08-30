<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Country;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Country>
 */
final class CountryFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Country::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement(['France', 'Germany', 'United States']),
            'continent' => ContinentFactory::random(),
        ];
    }
}

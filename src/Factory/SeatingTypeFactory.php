<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SeatingType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<SeatingType>
 */
final class SeatingTypeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return SeatingType::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement([
                'Sit Down', 'Inverted', 'Floorless', 'Stand Up', 'Flying', 'Wing', 'Bobsled',
            ]),
        ];
    }
}

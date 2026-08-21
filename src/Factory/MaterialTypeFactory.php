<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\MaterialType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MaterialType>
 */
final class MaterialTypeFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return MaterialType::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement(['Steel', 'Wood', 'Hybrid']),
        ];
    }
}

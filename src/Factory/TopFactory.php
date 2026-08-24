<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Top;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Top>
 */
final class TopFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Top::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->realText(30),
            'user' => UserFactory::random(),
            'main' => false,
        ];
    }
}

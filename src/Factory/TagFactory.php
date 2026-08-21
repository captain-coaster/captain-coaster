<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Tag;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Tag>
 */
final class TagFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Tag::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->word(),
            'type' => Tag::PRO,
        ];
    }
}

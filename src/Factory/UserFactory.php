<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/** @extends PersistentProxyObjectFactory<User> */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        $firstName = self::faker()->firstName();

        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => $firstName,
            'displayName' => $firstName.' '.self::faker()->lastName(),
            'preferredLocale' => 'en',
        ];
    }
}

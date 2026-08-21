<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        $firstName = self::faker()->firstName();
        $lastName = self::faker()->lastName();

        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstName' => $firstName,
            'lastName' => $lastName,
            // UserListener::prePersist() always overwrites this from firstName/lastName;
            // kept as a harmless default in case that listener behavior ever changes.
            'displayName' => $firstName.' '.$lastName,
            'preferredLocale' => 'en',
            // User::$enabled defaults to false; without this, UserChecker rejects
            // every fixture user as banned, and review listings (which filter on
            // u.enabled = 1) show no reviews at all.
            'enabled' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\RiddenCoaster;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<RiddenCoaster>
 */
final class RiddenCoasterFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return RiddenCoaster::class;
    }

    protected function defaults(): array
    {
        return [
            'coaster' => CoasterFactory::random(),
            'user' => UserFactory::random(),
            'value' => self::faker()->randomElement([0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0]),
            'review' => self::faker()->boolean(70) ? self::faker()->realText(200) : null,
            'language' => 'en',
            'riddenAt' => self::faker()->dateTimeBetween('-5 years', 'now'),
        ];
    }
}

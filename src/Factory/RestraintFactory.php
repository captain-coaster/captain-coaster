<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Restraint;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Restraint>
 */
final class RestraintFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Restraint::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement([
                'Lap Bar', 'Over-the-Shoulder Restraint', 'Lap Bar and Seatbelt', 'None',
            ]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Launch;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Launch>
 */
final class LaunchFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Launch::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->randomElement([
                'Chain Lift Hill', 'LSM Launch', 'LIM Launch', 'Hydraulic Launch', 'Cable Lift', 'Tire-Drive Lift',
            ]),
        ];
    }
}

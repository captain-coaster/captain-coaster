<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Coaster;
use App\Entity\Status;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Coaster>
 */
final class CoasterFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Coaster::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->word(),
            'park' => ParkFactory::random(),
            'materialType' => MaterialTypeFactory::random(),
            'seatingType' => SeatingTypeFactory::random(),
            'manufacturer' => ManufacturerFactory::random(),
            'restraint' => RestraintFactory::random(),
            'status' => StatusFactory::repository()->findOneBy(['name' => Status::OPERATING]),
            'openingDate' => self::faker()->dateTimeBetween('-40 years', '-1 year'),
            'height' => self::faker()->numberBetween(20, 140),
            'speed' => self::faker()->numberBetween(40, 150),
            'length' => self::faker()->numberBetween(500, 2000),
            'inversionsNumber' => self::faker()->numberBetween(0, 4),
            'enabled' => true,
        ];
    }
}

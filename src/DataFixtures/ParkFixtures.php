<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Factory\CountryFactory;
use App\Factory\ParkFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class ParkFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $france = CountryFactory::repository()->findOneBy(['name' => 'France']);
        $usa = CountryFactory::repository()->findOneBy(['name' => 'United States']);

        ParkFactory::createOne(['name' => 'Europa-Park', 'country' => $france, 'enabled' => true, 'latitude' => 48.266778, 'longitude' => 7.722244]);
        ParkFactory::createOne(['name' => 'Cedar Point', 'country' => $usa, 'enabled' => true, 'latitude' => 41.482224, 'longitude' => -82.684425]);
        ParkFactory::createOne(['name' => 'Six Flags Magic Mountain', 'country' => $usa, 'enabled' => true, 'latitude' => 34.423332, 'longitude' => -118.596664]);
    }

    public function getDependencies(): array
    {
        return [TaxonomyFixtures::class];
    }
}

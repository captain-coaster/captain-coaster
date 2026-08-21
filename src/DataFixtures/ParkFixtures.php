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

        ParkFactory::createOne(['name' => 'Europa-Park', 'country' => $france, 'enabled' => true]);
        ParkFactory::createOne(['name' => 'Cedar Point', 'country' => $usa, 'enabled' => true]);
        ParkFactory::createOne(['name' => 'Six Flags Magic Mountain', 'country' => $usa, 'enabled' => true]);
    }

    public function getDependencies(): array
    {
        return [TaxonomyFixtures::class];
    }
}

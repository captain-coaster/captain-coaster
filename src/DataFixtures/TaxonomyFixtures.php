<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Status;
use App\Entity\Tag;
use App\Factory\ContinentFactory;
use App\Factory\CountryFactory;
use App\Factory\LaunchFactory;
use App\Factory\ManufacturerFactory;
use App\Factory\MaterialTypeFactory;
use App\Factory\RestraintFactory;
use App\Factory\SeatingTypeFactory;
use App\Factory\StatusFactory;
use App\Factory\TagFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class TaxonomyFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Status "Operating" must be created first: id 1 is relied on elsewhere.
        // name holds the machine key (matched by CoasterRepository/ParkRepository status
        // filters and translated via the 'database' domain) - not a display string.
        StatusFactory::createOne(['name' => Status::OPERATING, 'type' => Status::OPERATING, 'isRateable' => true, 'order' => 1]);
        StatusFactory::createOne(['name' => Status::CLOSED_DEFINITELY, 'type' => Status::CLOSED_DEFINITELY, 'isRateable' => false, 'order' => 2]);

        $europe = ContinentFactory::createOne(['name' => 'Europe']);
        $northAmerica = ContinentFactory::createOne(['name' => 'North America']);

        CountryFactory::createOne(['name' => 'France', 'continent' => $europe]);
        CountryFactory::createOne(['name' => 'Germany', 'continent' => $europe]);
        CountryFactory::createOne(['name' => 'United States', 'continent' => $northAmerica]);

        foreach ([
            'Bolliger & Mabillard', 'Intamin', 'Vekoma', 'Rocky Mountain Construction',
            'Mack Rides', 'Gerstlauer', 'Zamperla', 'S&S - Sansei Technologies',
            'Great Coasters International', 'Premier Rides',
        ] as $name) {
            ManufacturerFactory::createOne(['name' => $name]);
        }

        foreach (['Steel', 'Wood', 'Hybrid'] as $name) {
            MaterialTypeFactory::createOne(['name' => $name]);
        }

        foreach (['Sit Down', 'Inverted', 'Floorless', 'Stand Up', 'Flying', 'Wing', 'Bobsled'] as $name) {
            SeatingTypeFactory::createOne(['name' => $name]);
        }

        foreach (['Lap Bar', 'Over-the-Shoulder Restraint', 'Lap Bar and Seatbelt', 'None'] as $name) {
            RestraintFactory::createOne(['name' => $name]);
        }

        foreach (['Chain Lift Hill', 'LSM Launch', 'LIM Launch', 'Hydraulic Launch', 'Cable Lift', 'Tire-Drive Lift'] as $name) {
            LaunchFactory::createOne(['name' => $name]);
        }

        foreach (['Airtime', 'Smoothness', 'Theming', 'Intensity', 'Great Views'] as $name) {
            TagFactory::createOne(['name' => $name, 'type' => Tag::PRO]);
        }

        foreach (['Rough Ride', 'Long Queue', 'Short Ride Time', 'Uncomfortable Restraints'] as $name) {
            TagFactory::createOne(['name' => $name, 'type' => Tag::CON]);
        }
    }
}

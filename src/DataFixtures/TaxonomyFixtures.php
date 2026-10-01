<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Continent;
use App\Entity\Country;
use App\Entity\Launch;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\Restraint;
use App\Entity\SeatingType;
use App\Entity\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Reference/vocabulary data for the contributor bootstrap DB (#318): real-world
 * industry facts (manufacturers, models, geography...), not private user data,
 * so real values are used directly instead of fictional placeholders.
 */
final class TaxonomyFixtures extends Fixture
{
    /** name => [ISO code, continent reference key] */
    private const array COUNTRIES = [
        'usa' => ['US', 'america'],
        'germany' => ['DE', 'europe'],
        'spain' => ['ES', 'europe'],
        'france' => ['FR', 'europe'],
    ];

    /** reference key => [code, English name] */
    private const array RESTRAINTS = [
        'lap' => ['restraint.lap', 'Lap bar'],
        'shoulder' => ['restraint.shoulder', 'Shoulder harness'],
        'flying' => ['restraint.flying', 'Flying restraint'],
    ];

    private const array LAUNCHES = [
        'chain' => ['launch.lift.chain', 'Chain lift hill'],
        'lsm' => ['launch.lim', 'Magnetic Launch (LIM/LSM)'],
    ];

    private const array MATERIAL_TYPES = [
        'steel' => ['material.steel', 'Steel'],
        'wood' => ['material.wood', 'Wood'],
    ];

    private const array SEATING_TYPES = [
        'sit_down' => 'Sit Down',
        'inverted' => 'Inverted',
        'flying' => 'Flying',
        'fourth_dimension' => '4th Dimension',
    ];

    private const array MANUFACTURERS = [
        'rmc' => 'Rocky Mountain Construction',
        'intamin' => 'Intamin',
        'mack' => 'Mack Rides',
        'bm' => 'Bolliger & Mabillard',
        'arrow' => 'Arrow Dynamics',
    ];

    private const array MODELS = [
        'hybrid' => 'Hybrid',
        'giga' => 'Giga Coaster',
        'hyper' => 'Hyper Coaster',
        'launch' => 'Launch Coaster',
        'fourth_dimension' => '4th Dimension Coaster',
        'flying' => 'Flying Coaster',
        'inverted' => 'Inverted Coaster',
    ];

    public function load(ObjectManager $manager): void
    {
        $this->loadGeography($manager);
        $this->loadStatus($manager);

        foreach (self::RESTRAINTS as $key => [$code, $name]) {
            $restraint = new Restraint()->setCode($code)->setName($name);
            $manager->persist($restraint);
            $this->addReference('restraint_'.$key, $restraint);
        }

        foreach (self::LAUNCHES as $key => [$code, $name]) {
            $launch = new Launch()->setCode($code)->setName($name);
            $manager->persist($launch);
            $this->addReference('launch_'.$key, $launch);
        }

        foreach (self::MATERIAL_TYPES as $key => [$code, $name]) {
            $materialType = new MaterialType()->setCode($code)->setName($name);
            $manager->persist($materialType);
            $this->addReference('material_'.$key, $materialType);
        }

        foreach (self::SEATING_TYPES as $key => $name) {
            $seatingType = new SeatingType()->setName($name);
            $manager->persist($seatingType);
            $this->addReference('seating_'.$key, $seatingType);
        }

        foreach (self::MANUFACTURERS as $key => $name) {
            $manufacturer = new Manufacturer()->setName($name);
            $manager->persist($manufacturer);
            $this->addReference('manufacturer_'.$key, $manufacturer);
        }

        foreach (self::MODELS as $key => $name) {
            $model = new Model()->setName($name);
            $manager->persist($model);
            $this->addReference('model_'.$key, $model);
        }

        $manager->flush();
    }

    private function loadGeography(ObjectManager $manager): void
    {
        $continents = [];
        foreach (['europe' => 'Europe', 'america' => 'America'] as $key => $name) {
            $continent = new Continent()->setCode('continent.'.$key)->setName($name);
            $manager->persist($continent);
            $continents[$key] = $continent;
        }

        foreach (self::COUNTRIES as $key => [$code, $continentKey]) {
            $country = new Country()->setCode($code)->setContinent($continents[$continentKey]);
            $manager->persist($country);
            $this->addReference('country_'.$key, $country);
        }
    }

    private function loadStatus(ObjectManager $manager): void
    {
        // Only the status actually used by the seeded coasters below -- all real,
        // currently operating rides.
        $status = new Status()
            ->setCode(Status::OPERATING)
            ->setName('Operating')
            ->setType('operational')
            ->setIsRateable(true)
            ->setOrder(4);
        $manager->persist($status);
        $this->addReference('status_operating', $status);
    }
}

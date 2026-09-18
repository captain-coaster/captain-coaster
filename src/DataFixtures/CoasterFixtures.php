<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Coaster;
use App\Entity\Launch;
use App\Entity\Manufacturer;
use App\Entity\MaterialType;
use App\Entity\Model;
use App\Entity\Park;
use App\Entity\Restraint;
use App\Entity\SeatingType;
use App\Entity\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * A dozen real, currently-operating coasters spread across the seeded parks --
 * public knowledge (RCDB-level facts), not scraped from production data. See
 * TaxonomyFixtures for the real-vs-fictional split.
 *
 * @phpstan-type CoasterSpec array{
 *     name: string, park: string, manufacturer: string, model: ?string,
 *     material: string, seating: string, restraint: string, launch: string,
 *     height: int, speed: int, length: int, inversions: int, openingDate: string,
 * }
 */
final class CoasterFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var array<string, CoasterSpec> */
    private const array COASTERS = [
        'steel_vengeance' => [
            'name' => 'Steel Vengeance', 'park' => 'cedar_point', 'manufacturer' => 'rmc', 'model' => 'hybrid',
            'material' => 'wood', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'chain',
            'height' => 61, 'speed' => 119, 'length' => 1622, 'inversions' => 4, 'openingDate' => '2018-05-05',
        ],
        'millennium_force' => [
            'name' => 'Millennium Force', 'park' => 'cedar_point', 'manufacturer' => 'intamin', 'model' => 'giga',
            'material' => 'steel', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'chain',
            'height' => 94, 'speed' => 150, 'length' => 2011, 'inversions' => 0, 'openingDate' => '2000-05-13',
        ],
        'blue_fire' => [
            'name' => 'Blue Fire Megacoaster', 'park' => 'europa_park', 'manufacturer' => 'mack', 'model' => 'launch',
            'material' => 'steel', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'lsm',
            'height' => 38, 'speed' => 100, 'length' => 1056, 'inversions' => 3, 'openingDate' => '2009-05-01',
        ],
        'silver_star' => [
            'name' => 'Silver Star', 'park' => 'europa_park', 'manufacturer' => 'bm', 'model' => 'hyper',
            'material' => 'steel', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'chain',
            'height' => 73, 'speed' => 130, 'length' => 1620, 'inversions' => 0, 'openingDate' => '2002-05-16',
        ],
        'wodan' => [
            'name' => 'Wodan - Timburcoaster', 'park' => 'europa_park', 'manufacturer' => 'mack', 'model' => null,
            'material' => 'wood', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'chain',
            'height' => 34, 'speed' => 100, 'length' => 1050, 'inversions' => 0, 'openingDate' => '2012-06-01',
        ],
        'twisted_colossus' => [
            'name' => 'Twisted Colossus', 'park' => 'six_flags_magic_mountain', 'manufacturer' => 'rmc', 'model' => 'hybrid',
            'material' => 'wood', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'chain',
            'height' => 27, 'speed' => 145, 'length' => 1615, 'inversions' => 0, 'openingDate' => '2015-05-22',
        ],
        'x2' => [
            'name' => 'X2', 'park' => 'six_flags_magic_mountain', 'manufacturer' => 'arrow', 'model' => 'fourth_dimension',
            'material' => 'steel', 'seating' => 'fourth_dimension', 'restraint' => 'shoulder', 'launch' => 'chain',
            'height' => 53, 'speed' => 122, 'length' => 967, 'inversions' => 2, 'openingDate' => '2002-01-12',
        ],
        'tatsu' => [
            'name' => 'Tatsu', 'park' => 'six_flags_magic_mountain', 'manufacturer' => 'bm', 'model' => 'flying',
            'material' => 'steel', 'seating' => 'flying', 'restraint' => 'flying', 'launch' => 'chain',
            'height' => 51, 'speed' => 100, 'length' => 1050, 'inversions' => 3, 'openingDate' => '2006-05-13',
        ],
        'shambhala' => [
            'name' => 'Shambhala', 'park' => 'portaventura', 'manufacturer' => 'bm', 'model' => 'giga',
            'material' => 'steel', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'chain',
            'height' => 76, 'speed' => 134, 'length' => 2534, 'inversions' => 0, 'openingDate' => '2012-05-12',
        ],
        'dragon_khan' => [
            'name' => 'Dragon Khan', 'park' => 'portaventura', 'manufacturer' => 'bm', 'model' => null,
            'material' => 'steel', 'seating' => 'sit_down', 'restraint' => 'shoulder', 'launch' => 'chain',
            'height' => 45, 'speed' => 110, 'length' => 1269, 'inversions' => 8, 'openingDate' => '1995-04-01',
        ],
        'toutatis' => [
            'name' => 'Toutatis', 'park' => 'parc_asterix', 'manufacturer' => 'intamin', 'model' => null,
            'material' => 'steel', 'seating' => 'sit_down', 'restraint' => 'lap', 'launch' => 'chain',
            'height' => 44, 'speed' => 100, 'length' => 1230, 'inversions' => 4, 'openingDate' => '2018-04-07',
        ],
        'oziris' => [
            'name' => 'OzIris', 'park' => 'parc_asterix', 'manufacturer' => 'bm', 'model' => 'inverted',
            'material' => 'steel', 'seating' => 'inverted', 'restraint' => 'shoulder', 'launch' => 'chain',
            'height' => 35, 'speed' => 85, 'length' => 950, 'inversions' => 6, 'openingDate' => '2012-04-07',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $status = $this->getReference('status_operating', Status::class);

        foreach (self::COASTERS as $key => $spec) {
            $coaster = new Coaster()
                ->setName($spec['name'])
                ->setPark($this->getReference('park_'.$spec['park'], Park::class))
                ->setManufacturer($this->getReference('manufacturer_'.$spec['manufacturer'], Manufacturer::class))
                ->setMaterialType($this->getReference('material_'.$spec['material'], MaterialType::class))
                ->setSeatingType($this->getReference('seating_'.$spec['seating'], SeatingType::class))
                ->setRestraint($this->getReference('restraint_'.$spec['restraint'], Restraint::class))
                ->setStatus($status)
                ->setHeight($spec['height'])
                ->setSpeed($spec['speed'])
                ->setLength($spec['length'])
                ->setInversionsNumber($spec['inversions'])
                ->setOpeningDate(new \DateTime($spec['openingDate']))
                ->setEnabled(true);

            $coaster->addLaunch($this->getReference('launch_'.$spec['launch'], Launch::class));

            if (null !== $spec['model']) {
                $coaster->setModel($this->getReference('model_'.$spec['model'], Model::class));
            }

            $manager->persist($coaster);
            $this->addReference('coaster_'.$key, $coaster);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TaxonomyFixtures::class, ParkFixtures::class];
    }
}

<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Factory\CoasterFactory;
use App\Factory\LaunchFactory;
use App\Factory\ParkFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class CoasterFixtures extends Fixture implements DependentFixtureInterface
{
    private const array COASTER_NAMES = [
        'Steel Vengeance', 'Millennium Force', 'Top Thrill 2', 'Maverick', 'GateKeeper', 'Raptor',
        'Fury 325', 'Intimidator 305', 'Twisted Colossus', 'Goliath', 'Full Throttle', 'Tatsu',
        'X2', 'Silver Star', 'Blue Fire', 'Wodan Timburcoo', 'Voltron Nevera', 'Alpina Blitz',
        'Nemesis', 'Wicker Man', 'Hyperia', 'Icon', 'The Smiler', 'Galactica',
    ];

    public function load(ObjectManager $manager): void
    {
        $names = self::COASTER_NAMES;
        shuffle($names);
        $parks = ParkFactory::repository()->findAll();

        foreach (\array_slice($names, 0, 20) as $i => $name) {
            $coaster = CoasterFactory::createOne([
                'name' => $name,
                'park' => $parks[$i % \count($parks)],
            ]);

            // ~half get 1-2 launch mechanisms; the rest stay chain-lift-only (Launch is optional).
            if (0 === $i % 2) {
                $coaster->addLaunch(LaunchFactory::random()->_real());
            }
        }

        // addLaunch() mutates ManyToMany collections after createOne() already
        // flushed; an explicit flush is needed to persist those join rows.
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TaxonomyFixtures::class, ParkFixtures::class];
    }
}

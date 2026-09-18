<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Country;
use App\Entity\Park;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * A handful of real, well-known parks -- public knowledge, not scraped from
 * production data. See TaxonomyFixtures for the real-vs-fictional split.
 */
final class ParkFixtures extends Fixture implements DependentFixtureInterface
{
    /** name => country reference key */
    private const array PARKS = [
        'cedar_point' => ['Cedar Point', 'country_usa'],
        'europa_park' => ['Europa-Park', 'country_germany'],
        'six_flags_magic_mountain' => ['Six Flags Magic Mountain', 'country_usa'],
        'portaventura' => ['PortAventura Park', 'country_spain'],
        'parc_asterix' => ['Parc Astérix', 'country_france'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::PARKS as $key => [$name, $countryReference]) {
            $park = new Park()
                ->setName($name)
                ->setCountry($this->getReference($countryReference, Country::class))
                ->setEnabled(true);
            $manager->persist($park);
            $this->addReference('park_'.$key, $park);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TaxonomyFixtures::class];
    }
}

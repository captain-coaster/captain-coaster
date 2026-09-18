<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Only the pro/con tags actually referenced by RiddenCoasterFixtures -- not the
 * full vocabulary from translations/database+intl-icu.*.yml.
 */
final class TagFixtures extends Fixture
{
    private const array PROS = ['airtimes', 'ejectors', 'launch', 'layout', 'theming', 'speed', 'comfort'];
    private const array CONS = ['short', 'pace'];

    public function load(ObjectManager $manager): void
    {
        foreach (self::PROS as $name) {
            $this->createTag($manager, Tag::PRO, $name);
        }

        foreach (self::CONS as $name) {
            $this->createTag($manager, Tag::CON, $name);
        }

        $manager->flush();
    }

    private function createTag(ObjectManager $manager, string $type, string $name): void
    {
        $tag = new Tag()->setType($type)->setName($type.'.'.$name);
        $manager->persist($tag);
        $this->addReference('tag_'.$type.'_'.$name, $tag);
    }
}

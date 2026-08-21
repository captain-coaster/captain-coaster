<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tag;
use App\Factory\CoasterFactory;
use App\Factory\RiddenCoasterFactory;
use App\Factory\TagFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class RiddenCoasterFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $coasters = CoasterFactory::repository()->findAll();
        $users = UserFactory::repository()->findAll();
        $proTags = TagFactory::repository()->findBy(['type' => Tag::PRO]);
        $conTags = TagFactory::repository()->findBy(['type' => Tag::CON]);

        foreach ($users as $user) {
            $ridden = $coasters;
            shuffle($ridden);
            $ridden = \array_slice($ridden, 0, random_int(8, 12));

            foreach ($ridden as $coaster) {
                $riddenCoaster = RiddenCoasterFactory::createOne([
                    'coaster' => $coaster,
                    'user' => $user,
                ]);

                if (null !== $riddenCoaster->getReview()) {
                    // _real() defaults to auto-refresh: calling it a second time here
                    // would reload from DB and silently drop the pending addPro()
                    // mutation before it's flushed. Reuse a single reference instead.
                    $real = $riddenCoaster->_real();
                    $real->addPro($proTags[array_rand($proTags)]->_real());
                    if (0 === random_int(0, 1)) {
                        $real->addCon($conTags[array_rand($conTags)]->_real());
                    }
                }
            }
        }

        // addPro()/addCon() mutate ManyToMany collections after createOne()
        // already flushed; an explicit flush is needed to persist those join rows.
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CoasterFixtures::class, UserFixtures::class];
    }
}

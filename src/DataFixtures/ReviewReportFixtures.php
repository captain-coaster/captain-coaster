<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Factory\ReviewReportFactory;
use App\Factory\RiddenCoasterFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class ReviewReportFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $reviewed = array_values(array_filter(
            RiddenCoasterFactory::repository()->findAll(),
            static fn ($riddenCoaster) => null !== $riddenCoaster->getReview(),
        ));
        $users = UserFactory::repository()->findAll();

        if ([] === $reviewed) {
            return;
        }

        shuffle($reviewed);

        foreach (\array_slice($reviewed, 0, min(10, \count($reviewed))) as $riddenCoaster) {
            $reporters = array_values(array_filter($users, static fn ($user) => $user->getId() !== $riddenCoaster->getUser()->getId()));

            ReviewReportFactory::createOne([
                'user' => $reporters[array_rand($reporters)],
                'review' => $riddenCoaster,
            ]);
        }
    }

    public function getDependencies(): array
    {
        return [RiddenCoasterFixtures::class];
    }
}

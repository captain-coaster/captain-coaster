<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TopCoaster;
use App\Factory\TopFactory;
use App\Factory\UserFactory;
use App\Repository\RiddenCoasterRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class TopFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly RiddenCoasterRepository $riddenCoasterRepository)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $users = UserFactory::repository()->findAll();

        foreach ($users as $user) {
            $ridden = $this->riddenCoasterRepository->findBy(['user' => $user->_real()], ['value' => 'DESC']);
            if ([] === $ridden) {
                continue;
            }

            $top = TopFactory::createOne([
                'name' => 'Top 10',
                'user' => $user,
                'main' => true,
            ]);

            $real = $top->_real();
            foreach (\array_slice($ridden, 0, 10) as $position => $riddenCoaster) {
                $topCoaster = new TopCoaster();
                $topCoaster->setCoaster($riddenCoaster->getCoaster());
                $topCoaster->setPosition($position + 1);
                $real->addTopCoaster($topCoaster);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RiddenCoasterFixtures::class];
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Coaster;
use App\Entity\Top;
use App\Entity\TopCoaster;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TopListService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Returns the user's ranking list (the one feeding the global ranking), creating it on first need. */
    public function getOrCreateRankingTop(User $user): Top
    {
        return $this->getOrCreate($user, Top::TYPE_RANKING, 'Top Coasters');
    }

    /** Returns the user's bucket list, creating it on first need. */
    public function getOrCreateBucketTop(User $user): Top
    {
        return $this->getOrCreate($user, Top::TYPE_BUCKET, 'Bucket list');
    }

    /** Appends a coaster to the bucket list. No-op if already present. Ridden coasters are allowed. */
    public function addToBucket(User $user, Coaster $coaster): void
    {
        $bucket = $this->getOrCreateBucketTop($user);

        $maxPosition = 0;
        foreach ($bucket->getTopCoasters() as $topCoaster) {
            if ($topCoaster->getCoaster()->getId() === $coaster->getId()) {
                return;
            }
            $maxPosition = max($maxPosition, $topCoaster->getPosition());
        }

        $topCoaster = new TopCoaster();
        $topCoaster->setCoaster($coaster);
        $topCoaster->setPosition($maxPosition + 1);
        $bucket->addTopCoaster($topCoaster);

        $this->entityManager->persist($topCoaster);
        $this->entityManager->flush();
    }

    public function removeFromBucket(User $user, Coaster $coaster): void
    {
        $bucket = $this->findBucketTop($user);

        if (!$bucket instanceof Top) {
            return;
        }

        foreach ($bucket->getTopCoasters() as $topCoaster) {
            if ($topCoaster->getCoaster()->getId() === $coaster->getId()) {
                $bucket->removeTopCoaster($topCoaster);
                $this->entityManager->remove($topCoaster);
                $this->entityManager->flush();

                return;
            }
        }
    }

    public function isInBucket(User $user, Coaster $coaster): bool
    {
        $bucket = $this->findBucketTop($user);

        if (!$bucket instanceof Top) {
            return false;
        }

        foreach ($bucket->getTopCoasters() as $topCoaster) {
            if ($topCoaster->getCoaster()->getId() === $coaster->getId()) {
                return true;
            }
        }

        return false;
    }

    private function findBucketTop(User $user): ?Top
    {
        return $this->entityManager->getRepository(Top::class)
            ->findOneBy(['user' => $user, 'type' => Top::TYPE_BUCKET]);
    }

    private function getOrCreate(User $user, string $type, string $name): Top
    {
        $top = $this->entityManager->getRepository(Top::class)
            ->findOneBy(['user' => $user, 'type' => $type]);

        if ($top instanceof Top) {
            return $top;
        }

        $top = new Top();
        $top->setUser($user);
        $top->setType($type);
        $top->setName($name);

        $this->entityManager->persist($top);
        $this->entityManager->flush();

        return $top;
    }
}

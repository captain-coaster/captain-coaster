<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Factory\CoasterFactory;
use App\Factory\RiddenCoasterFactory;
use App\Factory\UserFactory;
use App\Repository\RiddenCoasterRepository;

final class RiddenCoasterModerationQueryIntegrationTest extends RepositoryIntegrationTestCase
{
    public function testFindPendingAnalysisReturnsOnlyUnmoderatedReviews(): void
    {
        $coaster = CoasterFactory::createOne();
        $user = UserFactory::createOne();

        $pending = RiddenCoasterFactory::createOne([
            'coaster' => $coaster,
            'user' => $user,
            'review' => 'A genuinely thrilling ride with strong airtime.',
            'moderatedAt' => null,
        ]);

        $alreadyModerated = RiddenCoasterFactory::createOne([
            'coaster' => CoasterFactory::createOne(),
            'user' => $user,
            'review' => 'Already reviewed by moderation.',
            'moderatedAt' => new \DateTime(),
        ]);

        /** @var RiddenCoasterRepository $repository */
        $repository = $this->entityManager->getRepository(\App\Entity\RiddenCoaster::class);

        // Limit set well above any realistic base-fixture volume so this test's own
        // rows (created last, highest ids) are never pushed out of the id-ordered window.
        $result = $repository->findPendingAnalysis(null, 10000);
        $resultIds = array_map(static fn ($r) => $r->getId(), $result);

        $this->assertContains($pending->getId(), $resultIds);
        $this->assertNotContains($alreadyModerated->getId(), $resultIds);
    }
}

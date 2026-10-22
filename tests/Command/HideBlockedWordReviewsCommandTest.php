<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\HideBlockedWordReviewsCommand;
use App\Entity\Coaster;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Tests\EntityId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class HideBlockedWordReviewsCommandTest extends TestCase
{
    public function testAReviewWithABlockedWordIsHiddenWithAnAutoHiddenReport(): void
    {
        $blocked = $this->review(1, 'That launch is fucking great');
        $clean = $this->review(2, 'Putain, quel lancement');

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects($this->once())->method('flush');

        $tester = $this->tester([$blocked, $clean], $entityManager);
        $tester->execute([]);

        $this->assertFalse($blocked->isReviewEnabled());
        $this->assertNotNull($blocked->getAnalyzedAt());
        $this->assertTrue($clean->isReviewEnabled());

        $this->assertCount(1, $persisted);
        $report = $persisted[0];
        $this->assertInstanceOf(ReviewReport::class, $report);
        $this->assertSame(ReviewReport::REASON_BLOCKED_WORD, $report->getReason());
        $this->assertSame(ReviewReport::STATUS_AUTO_HIDDEN, $report->getStatus());
        $this->assertStringContainsString('1 review(s) hidden.', $tester->getDisplay());
    }

    public function testADryRunChangesNothing(): void
    {
        $blocked = $this->review(1, 'fuck');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $tester = $this->tester([$blocked], $entityManager);
        $tester->execute(['--dry-run' => true]);

        $this->assertTrue($blocked->isReviewEnabled());
        $this->assertStringContainsString('1 review(s) would be hidden.', $tester->getDisplay());
    }

    /** @param list<RiddenCoaster> $reviews */
    private function tester(array $reviews, EntityManagerInterface $entityManager): CommandTester
    {
        $byId = [];
        foreach ($reviews as $review) {
            $byId[$review->getId()] = $review;
        }

        $repository = $this->createMock(RiddenCoasterRepository::class);
        $repository->method('streamPublishedReviewTexts')->willReturn(array_map(static fn (RiddenCoaster $review): array => ['id' => (int) $review->getId(), 'review' => (string) $review->getReview()], $reviews));
        $repository->method('find')->willReturnCallback(static fn (int $id): ?RiddenCoaster => $byId[$id] ?? null);

        return new CommandTester(new HideBlockedWordReviewsCommand($repository, $entityManager));
    }

    private function review(int $id, string $text): RiddenCoaster
    {
        $review = new RiddenCoaster()
            ->setCoaster(new Coaster()->setName('Test Coaster'))
            ->setUser(EntityId::set(new User(), 1000 + $id)->setDisplayName('Jane'))
            ->setValue(3.0)
            ->setReview($text)
            ->setReviewEnabled(true);

        return EntityId::set($review, $id);
    }
}

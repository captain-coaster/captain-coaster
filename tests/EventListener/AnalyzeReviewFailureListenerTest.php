<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Coaster;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\EventListener\AnalyzeReviewFailureListener;
use App\Message\AnalyzeReviewMessage;
use App\Repository\RiddenCoasterRepository;
use App\Tests\EntityId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class AnalyzeReviewFailureListenerTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private AnalyzeReviewFailureListener $listener;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RiddenCoasterRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->listener = new AnalyzeReviewFailureListener($this->repository, $this->entityManager);
    }

    private function failure(object $message): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(new Envelope($message), 'async', new \RuntimeException('Bedrock unreachable'));
    }

    public function testDoesNothingWhileRetriesRemain(): void
    {
        $event = $this->failure(new AnalyzeReviewMessage(1));
        $event->setForRetry();

        $this->repository->expects($this->never())->method('find');

        $this->listener->__invoke($event);
    }

    public function testIgnoresOtherMessages(): void
    {
        $this->repository->expects($this->never())->method('find');

        $this->listener->__invoke($this->failure(new \stdClass()));
    }

    public function testDoesNothingWhenTheReviewNoLongerNeedsAnalysis(): void
    {
        $this->repository->method('find')->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');

        $this->listener->__invoke($this->failure(new AnalyzeReviewMessage(1)));
    }

    public function testKeepsTheReviewHiddenAndSendsItToTheQueueOnFinalFailure(): void
    {
        $review = $this->review();
        $this->repository->method('find')->with(1)->willReturn($review);

        $persisted = null;
        $this->entityManager->expects($this->once())->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted): void { $persisted = $entity; });
        $this->entityManager->expects($this->once())->method('flush');

        $this->listener->__invoke($this->failure(new AnalyzeReviewMessage(1)));

        $this->assertInstanceOf(ReviewReport::class, $persisted);
        $this->assertSame(ReviewReport::REASON_ANALYSIS_FAILED, $persisted->getReason());
        $this->assertSame(ReviewReport::STATUS_PENDING, $persisted->getStatus());
        $this->assertSame('Bedrock unreachable', $persisted->getAiExplanation());
        $this->assertFalse($review->isReviewEnabled());
        $this->assertNotNull($review->getAnalyzedAt());
    }

    private function review(): RiddenCoaster
    {
        return EntityId::set(new RiddenCoaster(), 1)
            ->setCoaster(new Coaster()->setName('Test Coaster'))
            ->setUser(EntityId::set(new User(), 7)->setDisplayName('Jane'))
            ->setReview('a review');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Coaster;
use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use App\EventListener\ReviewReportListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\MessageInterface;

class ReviewReportListenerTest extends TestCase
{
    private ChatterInterface&MockObject $chatter;
    private ReviewReportListener $listener;

    protected function setUp(): void
    {
        $this->chatter = $this->createMock(ChatterInterface::class);
        $this->listener = new ReviewReportListener($this->chatter);
    }

    public function testPostPersistDoesNotThrowWhenReportHasNoUser(): void
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $review = new RiddenCoaster();
        $review->setCoaster($coaster);
        $review->setValue(3.0);
        $review->setReview('some review text');

        $reviewReport = new ReviewReport();
        $reviewReport->setReview($review);
        $reviewReport->setReason(ReviewReport::REASON_TOXIC);
        // $user is intentionally left null: this simulates an AI-generated report,
        // which has no human reporter (see ReviewReportCrudController for the same
        // null = "🤖 AI moderation" convention).

        $eventArgs = new PostPersistEventArgs($reviewReport, $this->createMock(EntityManagerInterface::class));

        $this->chatter->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(MessageInterface::class));

        $this->listener->postPersist($reviewReport, $eventArgs);
    }
}

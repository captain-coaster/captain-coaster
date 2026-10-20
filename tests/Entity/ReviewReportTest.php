<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ReviewReport;
use PHPUnit\Framework\TestCase;

class ReviewReportTest extends TestCase
{
    public function testAiOnlyReasonsAreAccepted(): void
    {
        $report = new ReviewReport();

        foreach ([ReviewReport::REASON_TROLL, ReviewReport::REASON_TOXIC, ReviewReport::REASON_OFFTOPIC, ReviewReport::REASON_NOT_RIDDEN, ReviewReport::REASON_OTHER] as $reason) {
            $report->setReason($reason);
            $this->assertSame($reason, $report->getReason());
        }
    }

    public function testUserCanBeNullForSystemGeneratedReports(): void
    {
        $report = new ReviewReport();
        $report->setUser(null);

        $this->assertNull($report->getUser());
    }

    public function testAiConfidenceAndExplanationAreNullableAndSettable(): void
    {
        $report = new ReviewReport();

        $this->assertNull($report->getAiConfidence());
        $this->assertNull($report->getAiExplanation());

        $report->setAiConfidence('high');
        $report->setAiExplanation('Pure insult, no substance.');

        $this->assertSame('high', $report->getAiConfidence());
        $this->assertSame('Pure insult, no substance.', $report->getAiExplanation());
    }

    public function testOnlyPendingAndAutoHiddenReportsAreActionable(): void
    {
        foreach (ReviewReport::STATUSES as $status) {
            $expected = \in_array($status, [ReviewReport::STATUS_PENDING, ReviewReport::STATUS_AUTO_HIDDEN], true);
            $this->assertSame($expected, new ReviewReport()->setStatus($status)->isActionable(), $status);
        }
    }

    public function testAModeratorOverturningTheAiResolvesTheReportAgainAtTheirTime(): void
    {
        $report = new ReviewReport()->setStatus(ReviewReport::STATUS_AUTO_HIDDEN)->setResolvedAt(new \DateTime('-2 days'));

        $report->setStatus(ReviewReport::STATUS_APPROVED);

        $this->assertTrue($report->isResolved());
        $this->assertGreaterThan(new \DateTime('-1 minute'), $report->getResolvedAt());
    }

    public function testTheSnapshotIsShownOnceTheReviewIsGone(): void
    {
        $this->assertSame('snapshot', new ReviewReport()->setReviewContent('snapshot')->getDisplayContent());
    }
}

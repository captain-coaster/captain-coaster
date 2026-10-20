<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ReviewReport;
use App\Entity\RiddenCoaster;
use PHPUnit\Framework\TestCase;

class RiddenCoasterModerationTest extends TestCase
{
    private function analyzedPublishedReview(string $text): RiddenCoaster
    {
        return new RiddenCoaster()
            ->setReview($text)
            ->setReviewEnabled(true)
            ->setAnalyzedAt(new \DateTime());
    }

    public function testANewReviewIsHiddenUntilAnalyzed(): void
    {
        $review = new RiddenCoaster()->setReview('great ride');

        $this->assertFalse($review->isReviewEnabled());
        $this->assertNull($review->getAnalyzedAt());
        $this->assertTrue($review->needsAnalysis());
    }

    public function testEditingTheTextHidesItAgainUntilAnalyzed(): void
    {
        $review = $this->analyzedPublishedReview('original text');

        $review->setReview('edited text');

        $this->assertFalse($review->isReviewEnabled());
        $this->assertNull($review->getAnalyzedAt());
        $this->assertTrue($review->needsAnalysis());
    }

    public function testSavingTheSameTextKeepsItPublished(): void
    {
        $review = $this->analyzedPublishedReview('same text');
        $analyzedAt = $review->getAnalyzedAt();

        $review->setReview('same text');

        $this->assertTrue($review->isReviewEnabled());
        $this->assertSame($analyzedAt, $review->getAnalyzedAt());
        $this->assertFalse($review->needsAnalysis());
    }

    public function testARatingWithoutTextNeedsNoAnalysis(): void
    {
        $this->assertFalse(new RiddenCoaster()->needsAnalysis());
        $this->assertFalse(new RiddenCoaster()->setReview('   ')->needsAnalysis());
    }

    public function testAReviewHeldForAModeratorNeedsNoAnalysis(): void
    {
        $review = new RiddenCoaster()->setReview('borderline')->setAnalyzedAt(new \DateTime());

        $this->assertFalse($review->needsAnalysis());
    }

    public function testATextWaitingForTheAiOrAModeratorIsLocked(): void
    {
        $this->assertTrue(new RiddenCoaster()->setReview('pending')->isAwaitingModeration());

        $held = new RiddenCoaster()->setReview('held')->setAnalyzedAt(new \DateTime());
        $held->addReport(new ReviewReport()->setReason(ReviewReport::REASON_UNCERTAIN));
        $this->assertTrue($held->isAwaitingModeration());
    }

    public function testATextTheAiHidCanBeEdited(): void
    {
        $hidden = new RiddenCoaster()->setReview('vulgar')->setAnalyzedAt(new \DateTime());
        $hidden->addReport(new ReviewReport()->setReason(ReviewReport::REASON_TOXIC)->setStatus(ReviewReport::STATUS_AUTO_HIDDEN));

        $this->assertTrue($hidden->isReviewHidden());
        $this->assertFalse($hidden->isAwaitingModeration());
    }

    public function testAPublishedReviewOrARatingAloneIsNotLocked(): void
    {
        $this->assertFalse($this->analyzedPublishedReview('published')->isAwaitingModeration());
        $this->assertFalse(new RiddenCoaster()->isAwaitingModeration());
    }

    public function testAnAutoHiddenTextWithAPendingReportIsLocked(): void
    {
        $review = new RiddenCoaster()->setReview('vulgar')->setAnalyzedAt(new \DateTime());
        $review->addReport(new ReviewReport()->setReason(ReviewReport::REASON_TOXIC)->setStatus(ReviewReport::STATUS_AUTO_HIDDEN));
        $review->addReport(new ReviewReport()->setReason(ReviewReport::REASON_NOT_RIDDEN));

        $this->assertTrue($review->isAwaitingModeration());
    }

    public function testEditingTheTextArchivesItsOpenReports(): void
    {
        $review = new RiddenCoaster()->setReview('old')->setAnalyzedAt(new \DateTime());
        $review->addReport($memberReport = new ReviewReport()->setReason(ReviewReport::REASON_NOT_RIDDEN));
        $review->addReport($aiReport = new ReviewReport()->setReason(ReviewReport::REASON_TOXIC)->setStatus(ReviewReport::STATUS_AUTO_HIDDEN));
        $review->addReport($decided = new ReviewReport()->setReason(ReviewReport::REASON_SPAM)->setStatus(ReviewReport::STATUS_APPROVED));

        $review->setReview('new');

        $this->assertSame(ReviewReport::STATUS_SUPERSEDED, $memberReport->getStatus());
        $this->assertSame(ReviewReport::STATUS_SUPERSEDED, $aiReport->getStatus());
        $this->assertSame(ReviewReport::STATUS_APPROVED, $decided->getStatus());
        $this->assertTrue($review->needsAnalysis());
    }
}

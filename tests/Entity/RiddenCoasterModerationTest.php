<?php

declare(strict_types=1);

namespace App\Tests\Entity;

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
        $this->assertTrue(new RiddenCoaster()->setReview('held')->setAnalyzedAt(new \DateTime())->isAwaitingModeration());
    }

    public function testAPublishedReviewOrARatingAloneIsNotLocked(): void
    {
        $this->assertFalse($this->analyzedPublishedReview('published')->isAwaitingModeration());
        $this->assertFalse(new RiddenCoaster()->isAwaitingModeration());
    }
}

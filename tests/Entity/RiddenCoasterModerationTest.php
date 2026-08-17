<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use PHPUnit\Framework\TestCase;

class RiddenCoasterModerationTest extends TestCase
{
    public function testModeratedAtDefaultsToNull(): void
    {
        $review = new RiddenCoaster();

        $this->assertNull($review->getModeratedAt());
    }

    public function testSettingReviewTextResetsModeratedAt(): void
    {
        $review = new RiddenCoaster();
        $review->setCoaster(new Coaster());
        $review->setReview('original text');
        $review->setModeratedAt(new \DateTime());

        $this->assertNotNull($review->getModeratedAt());

        $review->setReview('edited text');

        $this->assertNull($review->getModeratedAt());
    }

    public function testSettingReviewToSameTextDoesNotResetModeratedAt(): void
    {
        $review = new RiddenCoaster();
        $review->setCoaster(new Coaster());
        $review->setReview('same text');
        $moderatedAt = new \DateTime();
        $review->setModeratedAt($moderatedAt);

        $review->setReview('same text');

        $this->assertSame($moderatedAt, $review->getModeratedAt());
    }
}

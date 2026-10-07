<?php

declare(strict_types=1);

namespace App\Tests\Service\Home;

use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\RiddenCoasterRepository;
use App\Service\Home\ReviewPicker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReviewPickerTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $repository;
    private ReviewPicker $picker;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RiddenCoasterRepository::class);
        $this->repository->method('findReputedAuthorIds')->willReturn([1, 2, 3]);
        $this->picker = new ReviewPicker($this->repository);
    }

    private function review(int $authorId, string $language, int $coasterId): RiddenCoaster
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($authorId);
        $coaster = $this->createStub(Coaster::class);
        $coaster->method('getId')->willReturn($coasterId);

        return new RiddenCoaster()->setUser($user)->setCoaster($coaster)->setLanguage($language);
    }

    /** @param list<RiddenCoaster> $pool latest first */
    private function pool(array $pool): void
    {
        $this->repository->method('findRecentReviewsByAuthors')->willReturn($pool);
    }

    public function testKeepsTheLatestReviewOfEachAuthor(): void
    {
        $latest = $this->review(1, 'en', 10);
        $other = $this->review(2, 'en', 12);
        $this->pool([$latest, $this->review(1, 'en', 11), $other]);

        $this->assertSame([$latest, $other], $this->picker->pick(['en'], [], 3));
    }

    public function testReaderLanguagesComeBeforeTheEnglishFallback(): void
    {
        $english = $this->review(1, 'en', 10);
        $french = $this->review(2, 'fr', 11);
        $this->pool([$english, $french]);

        $this->assertSame([$french, $english], $this->picker->pick(['fr'], [], 3));
    }

    public function testOtherLanguagesAreLeftOut(): void
    {
        $this->pool([$this->review(1, 'de', 10), $this->review(2, 'es', 11)]);

        $this->assertSame([], $this->picker->pick(['fr'], [], 3));
    }

    public function testSkipsCoastersAlreadyFeatured(): void
    {
        $kept = $this->review(2, 'en', 11);
        $this->pool([$this->review(1, 'en', 10), $kept]);

        $this->assertSame([$kept], $this->picker->pick(['en'], [10], 3));
    }

    public function testStopsAtTheLimit(): void
    {
        $this->pool([$this->review(1, 'en', 10), $this->review(2, 'en', 11), $this->review(3, 'en', 12)]);

        $this->assertCount(2, $this->picker->pick(['en'], [], 2));
    }

    public function testPreloadsTheTagsOfThePickedReviews(): void
    {
        $picked = $this->review(1, 'en', 10);
        $this->pool([$picked, $this->review(2, 'de', 11)]);

        $this->repository->expects($this->once())->method('preloadTags')->with([$picked], 300);

        $this->picker->pick(['en'], [], 3);
    }
}

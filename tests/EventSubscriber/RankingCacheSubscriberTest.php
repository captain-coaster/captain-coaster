<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Event\RankingPublishedEvent;
use App\EventSubscriber\RankingCacheSubscriber;
use App\Repository\RankingRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RankingCacheSubscriberTest extends TestCase
{
    private RankingRepository&MockObject $rankingRepository;
    private RankingCacheSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->rankingRepository = $this->createMock(RankingRepository::class);
        $this->subscriber = new RankingCacheSubscriber($this->rankingRepository);
    }

    public function testSubscribesToRankingPublishedEvent(): void
    {
        $this->assertSame(
            [RankingPublishedEvent::class => 'onRankingPublished'],
            RankingCacheSubscriber::getSubscribedEvents()
        );
    }

    public function testOnRankingPublishedClearsTheRankingCache(): void
    {
        $this->rankingRepository->expects($this->once())->method('clearCache');

        $this->subscriber->onRankingPublished(new RankingPublishedEvent());
    }
}

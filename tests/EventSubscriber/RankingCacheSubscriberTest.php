<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Event\RankingComputedEvent;
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

    public function testSubscribesToRankingComputedEvent(): void
    {
        $this->assertSame(
            [RankingComputedEvent::class => 'onRankingComputed'],
            RankingCacheSubscriber::getSubscribedEvents()
        );
    }

    public function testOnRankingComputedClearsTheRankingCache(): void
    {
        $this->rankingRepository->expects($this->once())->method('clearCache');

        $this->subscriber->onRankingComputed(new RankingComputedEvent());
    }
}

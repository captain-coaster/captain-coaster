<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\RankingComputedEvent;
use App\Repository\RankingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invalidates RankingRepository's cached findCurrent()/findPrevious() as
 * soon as a new ranking is persisted, instead of waiting out their TTL.
 */
class RankingCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RankingRepository $rankingRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RankingComputedEvent::class => 'onRankingComputed',
        ];
    }

    public function onRankingComputed(RankingComputedEvent $event): void
    {
        $this->rankingRepository->clearCache();
    }
}

<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\RankingPublishedEvent;
use App\Repository\RankingRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears the cached ranking queries as soon as a ranking is published, instead of waiting out their TTL.
 */
class RankingCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RankingRepository $rankingRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RankingPublishedEvent::class => 'onRankingPublished',
        ];
    }

    public function onRankingPublished(RankingPublishedEvent $event): void
    {
        $this->rankingRepository->clearCache();
    }
}

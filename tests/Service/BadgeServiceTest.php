<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Badge;
use App\Entity\Coaster;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Event\BadgeAwardedEvent;
use App\Repository\BadgeRepository;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BadgeServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private BadgeRepository&MockObject $badgeRepository;
    private BadgeService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->badgeRepository = $this->createMock(BadgeRepository::class);

        $this->service = new BadgeService($this->em, $this->eventDispatcher, $this->badgeRepository);
    }

    public function testGiveDispatchesBadgeAwardedEventForANewBadge(): void
    {
        $user = $this->userWithOneRating();
        $badge = new Badge();
        $this->badgeRepository->method('findOneBy')->with(['name' => BadgeService::BADGE_RATING_1])->willReturn($badge);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(
                static fn (object $event) => $event instanceof BadgeAwardedEvent
                    && $event->user === $user
                    && BadgeService::BADGE_RATING_1 === $event->badgeName
            ));

        $this->service->give($user);

        $this->assertTrue($user->getBadges()->contains($badge));
    }

    public function testGiveDoesNotDispatchAgainForABadgeTheUserAlreadyHas(): void
    {
        $user = $this->userWithOneRating();
        $badge = new Badge();
        $user->addBadge($badge);
        $this->badgeRepository->method('findOneBy')->willReturn($badge);

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->service->give($user);
    }

    private function userWithOneRating(): User
    {
        $user = new User();
        $user->setEmail('rider@example.com');

        $coaster = new Coaster();
        $coaster->setName('Blue Fire Megacoaster');

        $rating = new RiddenCoaster();
        $rating->setCoaster($coaster);

        $user->addRating($rating);

        return $user;
    }
}

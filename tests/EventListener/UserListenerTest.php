<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\UserListener;
use App\Service\HeroService;
use App\Service\ImageManager;
use App\Service\ProfilePictureManager;
use App\Service\SearchCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserListenerTest extends TestCase
{
    /** @return iterable<string, array{array<string, array{mixed, mixed}>, bool}> */
    public static function changeSets(): iterable
    {
        yield 'renamed' => [['firstName' => ['Old', 'New']], true];
        yield 'display format' => [['displayNameFormat' => ['full', 'short']], true];
        yield 'disabled' => [['enabled' => [true, false]], true];
        yield 'signed in' => [['lastLogin' => [null, new \DateTime()]], false];
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    #[DataProvider('changeSets')]
    public function testClearsSearchCacheOnceWhenASearchableFieldChanges(array $changeSet, bool $cleared): void
    {
        $user = new User()->setFirstName('New');
        $searchCache = $this->createMock(SearchCacheService::class);
        $searchCache->expects($cleared ? $this->once() : $this->never())->method('invalidateSearchCache');
        $listener = new UserListener($this->createStub(ProfilePictureManager::class), $searchCache, $this->createStub(ImageManager::class), $this->createStub(HeroService::class));

        $listener->preUpdate($user, new PreUpdateEventArgs($user, $this->createStub(EntityManagerInterface::class), $changeSet));
        $listener->postUpdate();
        // The next write, a sign-in, must not clear it again
        $listener->postUpdate();
    }

    /** @return iterable<string, array{array<string, array{mixed, mixed}>, bool}> */
    public static function enabledChanges(): iterable
    {
        yield 'banned' => [['enabled' => [true, false]], true];
        yield 'restored' => [['enabled' => [false, true]], true];
        yield 'renamed' => [['firstName' => ['Old', 'New']], false];
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    #[DataProvider('enabledChanges')]
    public function testRecomputesLikesMainImagesAndHeroOnceAfterTheFlushWhenEnabledChanges(array $changeSet, bool $recomputed): void
    {
        $user = new User()->setFirstName('New');
        $images = $this->createMock(ImageManager::class);
        $images->expects($recomputed ? $this->once() : $this->never())->method('updateLikeCounters');
        $images->expects($recomputed ? $this->once() : $this->never())->method('setMainImages');
        $hero = $this->createMock(HeroService::class);
        $hero->expects($recomputed ? $this->once() : $this->never())->method('invalidate');
        $listener = new UserListener($this->createStub(ProfilePictureManager::class), $this->createStub(SearchCacheService::class), $images, $hero);

        $listener->preUpdate($user, new PreUpdateEventArgs($user, $this->createStub(EntityManagerInterface::class), $changeSet));
        $listener->postFlush();
        // The next flush, a sign-in, must not recompute again
        $listener->postFlush();
    }
}

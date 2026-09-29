<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\UserListener;
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
        $listener = new UserListener($this->createStub(ProfilePictureManager::class), $searchCache);

        $listener->preUpdate($user, new PreUpdateEventArgs($user, $this->createStub(EntityManagerInterface::class), $changeSet));
        $listener->postUpdate();
        // The next write, a sign-in, must not clear it again
        $listener->postUpdate();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\UserListener;
use App\Service\ProfilePictureManager;
use App\Service\SearchCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserListenerTest extends TestCase
{
    /** @return iterable<string, array{array<string, array{mixed, mixed}>, bool}> */
    public static function changeSets(): iterable
    {
        yield 'renamed' => [['displayName' => ['Old', 'New']], true];
        yield 'first name' => [['firstName' => ['Old', 'New']], true];
        yield 'disabled' => [['enabled' => [true, false]], true];
        yield 'signed in' => [['lastLogin' => [null, new \DateTime()]], false];
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    #[DataProvider('changeSets')]
    public function testClearsSearchCacheWhenSearchableFieldsChange(array $changeSet, bool $cleared): void
    {
        $user = new User();
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changeSet);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $searchCache = $this->createMock(SearchCacheService::class);
        $searchCache->expects($cleared ? $this->once() : $this->never())->method('invalidateSearchCache');

        new UserListener($this->createStub(ProfilePictureManager::class), $searchCache)
            ->postUpdate($user, new PostUpdateEventArgs($user, $entityManager));
    }
}

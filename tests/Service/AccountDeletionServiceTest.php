<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\LikedImageRepository;
use App\Repository\ReviewUpvoteRepository;
use App\Service\AccountDeletionService;
use App\Service\ProfilePictureManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AccountDeletionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private LikedImageRepository&MockObject $likedImageRepository;
    private ReviewUpvoteRepository&MockObject $reviewUpvoteRepository;
    private ProfilePictureManager&MockObject $profilePictureManager;
    private AccountDeletionService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->likedImageRepository = $this->createMock(LikedImageRepository::class);
        $this->reviewUpvoteRepository = $this->createMock(ReviewUpvoteRepository::class);
        $this->profilePictureManager = $this->createMock(ProfilePictureManager::class);

        $this->service = new AccountDeletionService(
            $this->entityManager,
            $this->logger,
            $this->likedImageRepository,
            $this->reviewUpvoteRepository,
            $this->profilePictureManager
        );
    }

    public function testScheduleAccountDeletionSetsDeletedAtAndDisablesUser(): void
    {
        $user = $this->createUser();

        $this->entityManager->expects($this->once())->method('persist')->with($user);
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info');

        $this->service->scheduleAccountDeletion($user);

        $this->assertNotNull($user->getDeletedAt());
        $this->assertFalse($user->isEnabled());
    }

    public function testScheduleAccountDeletionSetsDeletedAtToCurrentTime(): void
    {
        $user = $this->createUser();
        $before = new \DateTime();

        $this->service->scheduleAccountDeletion($user);

        $after = new \DateTime();
        $this->assertGreaterThanOrEqual($before, $user->getDeletedAt());
        $this->assertLessThanOrEqual($after, $user->getDeletedAt());
    }

    public function testPermanentlyDeleteAccountRemovesUser(): void
    {
        $user = $this->createUser();

        $this->entityManager->expects($this->once())->method('remove')->with($user);
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info');

        $this->service->permanentlyDeleteAccount($user);
    }

    public function testPermanentlyDeleteAccountLogsUserInfo(): void
    {
        $user = $this->createUser(42, 'test@example.com');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Account permanently deleted',
                $this->callback(function (array $context) {
                    return 42 === $context['user_id'] && 'test@example.com' === $context['email'];
                })
            );

        $this->service->permanentlyDeleteAccount($user);
    }

    public function testPurgeUserDataClearsUserContentButKeepsIdentity(): void
    {
        $user = $this->createUser(42, 'banned@example.com');
        $user->setDisplayName('John Doe');
        $user->setProfilePicture('pp_42_abc123.jpg');

        $this->likedImageRepository->expects($this->once())->method('deleteByUser')->with($user);
        $this->reviewUpvoteRepository->expects($this->once())->method('deleteByUser')->with($user);
        $this->profilePictureManager->expects($this->once())->method('deleteProfilePicture')->with('pp_42_abc123.jpg');
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('info')->with(
            'Banned user data purged',
            $this->callback(fn (array $context) => 42 === $context['user_id'] && 'banned@example.com' === $context['email'])
        );

        $this->service->purgeUserData($user);

        $this->assertSame('John Doe', $user->getDisplayName());
        $this->assertNull($user->getProfilePicture());
        $this->assertSame('banned@example.com', $user->getEmail());
    }

    private function createUser(?int $id = 1, string $email = 'user@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setEnabled(true);

        if (null !== $id) {
            $reflection = new \ReflectionClass($user);
            $property = $reflection->getProperty('id');
            $property->setValue($user, $id);
        }

        return $user;
    }
}

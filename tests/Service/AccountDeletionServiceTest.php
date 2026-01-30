<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AccountDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AccountDeletionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private AccountDeletionService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new AccountDeletionService(
            $this->entityManager,
            $this->logger
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

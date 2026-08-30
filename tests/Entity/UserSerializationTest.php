<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Notification;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;

/**
 * The security token is serialized into the Redis session on every response
 * (ContextListener::onKernelResponse). Without a narrowed __serialize(), the
 * whole User object graph goes with it — production sessions reached 1.9 MB.
 */
final class UserSerializationTest extends TestCase
{
    private const int MAX_TOKEN_BYTES = 4096;

    public function testSessionTokenStaysSmallWhenCollectionsAreLoaded(): void
    {
        $token = new RememberMeToken($this->userWithNotifications(1000), 'main');

        $this->assertLessThan(
            self::MAX_TOKEN_BYTES,
            \strlen(serialize($token)),
            'Loaded collections must not end up in the session token.'
        );
    }

    public function testLoadedCollectionsDoNotAppearInTheBlob(): void
    {
        $blob = serialize(new RememberMeToken($this->userWithNotifications(10), 'main'));

        $this->assertFalse(
            str_contains($blob, Notification::class),
            'The session token still carries Notification entities.'
        );
    }

    /** Fields ContextListener and AbstractToken::hasUserChanged() rely on: a mismatch logs everyone out. */
    #[DataProvider('securityCriticalFields')]
    public function testRoundTripPreservesSecurityCriticalField(string $getter): void
    {
        $user = $this->userWithNotifications(5);

        /** @var User $restored */
        $restored = unserialize(serialize($user));

        $this->assertSame($user->$getter(), $restored->$getter());
    }

    /** @return iterable<string, array{string}> */
    public static function securityCriticalFields(): iterable
    {
        yield 'id' => ['getId'];
        yield 'user identifier' => ['getUserIdentifier'];
        yield 'enabled' => ['isEnabled'];
        yield 'deleted at' => ['getDeletedAt'];
    }

    public function testRoundTripPreservesRoles(): void
    {
        $user = $this->userWithNotifications(5);
        $user->setRoles(['ROLE_ADMIN']);

        /** @var User $restored */
        $restored = unserialize(serialize($user));

        $this->assertSame($user->getRoles(), $restored->getRoles());
    }

    /** unserialize() skips the constructor, so the collections must be re-initialised by hand. */
    public function testRestoredCollectionsAreUsableAndEmpty(): void
    {
        /** @var User $restored */
        $restored = unserialize(serialize($this->userWithNotifications(5)));

        $this->assertCount(0, $restored->getNotifications());
        $this->assertCount(0, $restored->getRatings());
        $this->assertCount(0, $restored->getTops());
        $this->assertCount(0, $restored->getBadges());
        $this->assertCount(0, $restored->getImages());
    }

    /**
     * Sessions written before __serialize() existed still hold the full object
     * graph. PHP hands that whole array to __unserialize(), which must cope
     * with it rather than fatal — those sessions heal on their next write.
     */
    public function testLegacySessionPayloadIsAccepted(): void
    {
        $user = $this->userWithNotifications(5);
        $legacyPayload = (array) $user;

        $restored = new User();
        $restored->__unserialize($legacyPayload);

        $this->assertSame($user->getUserIdentifier(), $restored->getUserIdentifier());
        $this->assertCount(0, $restored->getNotifications());
    }

    private function userWithNotifications(int $count): User
    {
        $user = new User();
        $user->setEmail('rider@example.com');
        $user->setDisplayName('Rider');
        $user->setSlug('rider');

        for ($i = 0; $i < $count; ++$i) {
            $notification = new Notification();
            $notification->setUser($user);
            $notification->setMessage('notif.ranking.messageWithNewCoaster');
            $notification->setParameter('Blue Fire Megacoaster');
            $notification->setType('ranking');
            $notification->setIsRead(true);
            $notification->setCreatedAt(new \DateTime());

            $user->addNotification($notification);
        }

        return $user;
    }
}

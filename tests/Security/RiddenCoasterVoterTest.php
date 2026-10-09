<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Security\RiddenCoasterVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class RiddenCoasterVoterTest extends TestCase
{
    #[DataProvider('provideVotes')]
    public function testVote(string $attribute, bool $owner, int $expected): void
    {
        $user = new User();
        $rating = new RiddenCoaster()->setUser($owner ? $user : new User());

        $this->assertSame($expected, new RiddenCoasterVoter()->vote(new UsernamePasswordToken($user, 'main'), $rating, [$attribute]));
    }

    /** @return iterable<string, array{string, bool, int}> */
    public static function provideVotes(): iterable
    {
        yield 'rider updates their rating' => [RiddenCoasterVoter::UPDATE, true, VoterInterface::ACCESS_GRANTED];
        yield 'nobody else updates it' => [RiddenCoasterVoter::UPDATE, false, VoterInterface::ACCESS_DENIED];
        yield 'rider deletes their rating' => [RiddenCoasterVoter::DELETE, true, VoterInterface::ACCESS_GRANTED];
        yield 'nobody else deletes it' => [RiddenCoasterVoter::DELETE, false, VoterInterface::ACCESS_DENIED];
        yield 'an unknown attribute is left to other voters' => ['view', true, VoterInterface::ACCESS_ABSTAIN];
    }

    public function testAnonymousVisitorIsDenied(): void
    {
        $rating = new RiddenCoaster()->setUser(new User());

        $this->assertSame(VoterInterface::ACCESS_DENIED, new RiddenCoasterVoter()->vote(new NullToken(), $rating, [RiddenCoasterVoter::DELETE]));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Top;
use App\Entity\User;
use App\Security\TopVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class TopVoterTest extends TestCase
{
    #[DataProvider('provideVotes')]
    public function testVote(string $attribute, bool $owner, bool $main, int $expected): void
    {
        $user = new User();
        $top = new Top()->setUser($owner ? $user : new User())->setMain($main);

        $this->assertSame($expected, new TopVoter()->vote(new UsernamePasswordToken($user, 'main'), $top, [$attribute]));
    }

    /** @return iterable<string, array{string, bool, bool, int}> */
    public static function provideVotes(): iterable
    {
        yield 'owner edits their main Top' => [TopVoter::EDIT, true, true, VoterInterface::ACCESS_GRANTED];
        yield 'owner edits another of their Tops' => [TopVoter::EDIT, true, false, VoterInterface::ACCESS_GRANTED];
        yield 'nobody else edits a Top' => [TopVoter::EDIT, false, false, VoterInterface::ACCESS_DENIED];
        yield 'the main Top has no details to edit' => [TopVoter::EDIT_DETAILS, true, true, VoterInterface::ACCESS_DENIED];
        yield 'owner edits the details of another Top' => [TopVoter::EDIT_DETAILS, true, false, VoterInterface::ACCESS_GRANTED];
        yield 'nobody else edits the details' => [TopVoter::EDIT_DETAILS, false, false, VoterInterface::ACCESS_DENIED];
        yield 'the main Top is never deleted' => [TopVoter::DELETE, true, true, VoterInterface::ACCESS_DENIED];
        yield 'owner deletes another Top' => [TopVoter::DELETE, true, false, VoterInterface::ACCESS_GRANTED];
        yield 'nobody else deletes a Top' => [TopVoter::DELETE, false, false, VoterInterface::ACCESS_DENIED];
        yield 'an unknown attribute is left to other voters' => ['view', true, false, VoterInterface::ACCESS_ABSTAIN];
    }

    public function testAnonymousVisitorIsDenied(): void
    {
        $top = new Top()->setUser(new User())->setMain(false);

        $this->assertSame(VoterInterface::ACCESS_DENIED, new TopVoter()->vote(new NullToken(), $top, [TopVoter::EDIT]));
    }

    public function testAnotherSubjectIsLeftToOtherVoters(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, new TopVoter()->vote(new UsernamePasswordToken(new User(), 'main'), new User(), [TopVoter::EDIT]));
    }
}

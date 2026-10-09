<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Coaster;
use App\Entity\Status;
use App\Entity\User;
use App\Security\CoasterVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class CoasterVoterTest extends TestCase
{
    public function testRiderRatesACoasterWhoseStatusIsRateable(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->coaster(rateable: true)));
    }

    public function testNobodyRatesACoasterWhoseStatusIsNotRateable(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->coaster(rateable: false)));
    }

    public function testAnonymousVisitorIsDenied(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, new CoasterVoter()->vote(new NullToken(), $this->coaster(rateable: true), [CoasterVoter::RATE]));
    }

    public function testAnotherAttributeIsLeftToOtherVoters(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->coaster(rateable: true), 'edit'));
    }

    private function vote(Coaster $coaster, string $attribute = CoasterVoter::RATE): int
    {
        return new CoasterVoter()->vote(new UsernamePasswordToken(new User(), 'main'), $coaster, [$attribute]);
    }

    private function coaster(bool $rateable): Coaster
    {
        return new Coaster()->setStatus(new Status()->setIsRateable($rateable));
    }
}

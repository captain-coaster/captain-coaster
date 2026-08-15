<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\Country;
use App\Entity\Park;
use App\Entity\RiddenCoaster;
use App\Entity\User;
use App\Repository\CoasterRepository;
use App\Repository\CountryRepository;
use App\Repository\ParkRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\CaptainScore\CaptainScores;
use App\Service\CaptainScore\UserCaptainScoreService;
use App\Service\StatService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RiddenCoasterRepository&MockObject $ridden;
    private ParkRepository&MockObject $parks;
    private CountryRepository&MockObject $countries;
    private CoasterRepository&MockObject $coasters;
    private UserCaptainScoreService&MockObject $captainScores;
    private StatService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->ridden = $this->createMock(RiddenCoasterRepository::class);
        $this->parks = $this->createMock(ParkRepository::class);
        $this->countries = $this->createMock(CountryRepository::class);
        $this->coasters = $this->createMock(CoasterRepository::class);
        $this->captainScores = $this->createMock(UserCaptainScoreService::class);

        $this->em->method('getRepository')->willReturnCallback(fn (string $class) => match ($class) {
            RiddenCoaster::class => $this->ridden,
            Park::class => $this->parks,
            Country::class => $this->countries,
            Coaster::class => $this->coasters,
            default => $this->fail('Unexpected repository: '.$class),
        });

        $this->captainScores->method('forUser')->willReturn(new CaptainScores(0, null, null));

        $this->service = new StatService($this->em, $this->captainScores);
    }

    public function testGetProfileStatsReturnsEmptyForUserWithNoRides(): void
    {
        $this->ridden->method('countForUser')->willReturn(0);

        self::assertSame([], $this->service->getProfileStats($this->createMock(User::class)));
    }

    public function testScoreIsRiddenCountAndDrivesRank(): void
    {
        $this->primeRepositories(ridden: 120, newThisYear: 12);

        $stats = $this->service->getProfileStats($this->createMock(User::class));

        // Captain Score block — ridden count drives the rank tier;
        // CQS/CSS are computed by UserCaptainScoreService and surfaced here.
        self::assertSame(120, $stats['score']['ridden']);
        self::assertNull($stats['score']['quality']);
        self::assertNull($stats['score']['strength']);
        self::assertSame(0, $stats['score']['ranked_count']);

        // 120 lands in the "enthusiast" tier (50–149).
        self::assertSame('enthusiast', $stats['rank']['key']);
        self::assertSame('rider', $stats['rank']['next_key']);
        self::assertSame(150, $stats['rank']['next_at']);
        self::assertSame(30, $stats['rank']['remaining']);
        // (120 - 50) / (150 - 50) = 70%
        self::assertSame(70, $stats['rank']['progress_pct']);

        // Milestone is ridden-coaster based: 120 → next 200, prev 100.
        self::assertSame(200, $stats['milestone']['next']);
        self::assertSame(80, $stats['milestone']['remaining']);
        self::assertSame(20, $stats['milestone']['progress_pct']);

        self::assertSame(2026, $stats['this_year']['year']);
        self::assertSame(12, $stats['this_year']['new_coasters']);
    }

    #[DataProvider('rankTierProvider')]
    public function testComputeRankTiers(int $score, string $expectedKey, ?string $expectedNext): void
    {
        $rank = $this->service->computeRank($score);

        self::assertSame($expectedKey, $rank['key']);
        self::assertSame($expectedNext, $rank['next_key']);
    }

    /** @return array<string, array{int, string, string|null}> */
    public static function rankTierProvider(): array
    {
        return [
            'rookie' => [10, 'rookie', 'enthusiast'],
            'enthusiast' => [100, 'enthusiast', 'rider'],
            'rider' => [200, 'rider', 'veteran'],
            'veteran' => [400, 'veteran', 'expert'],
            'expert' => [700, 'expert', 'legend'],
            'legend' => [1200, 'legend', null],
        ];
    }

    #[DataProvider('milestoneProvider')]
    public function testMilestoneTargets(int $ridden, int $expectedNext): void
    {
        $this->primeRepositories(ridden: $ridden, newThisYear: 0);

        $stats = $this->service->getProfileStats($this->createMock(User::class));

        self::assertSame($expectedNext, $stats['milestone']['next']);
    }

    /** @return array<string, array{int, int}> */
    public static function milestoneProvider(): array
    {
        return [
            'below 50' => [30, 50],
            'exactly 50' => [50, 100],
            'in first hundred' => [80, 100],
            'second hundred' => [150, 200],
            'on a hundred' => [200, 300],
        ];
    }

    private function primeRepositories(int $ridden, int $newThisYear): void
    {
        $this->ridden->method('countForUser')->willReturn($ridden);
        $this->ridden->method('countRatedForUser')->willReturn($ridden);
        $this->ridden->method('countNewCoastersThisYear')->willReturn($newThisYear);
        $this->ridden->method('countTotalRidesThisYear')->willReturn($newThisYear);
        $this->ridden->method('findMostRiddenCountry')->willReturn(['name' => 'France', 'nb' => 10]);
        $this->ridden->method('countTop100ForUser')->willReturn(['nb_top100' => 5, 'nb_top100_operating' => 4]);
        $this->ridden->method('getMostRiddenManufacturer')->willReturn(['name' => 'B&M', 'nb' => 20]);
        $this->ridden->method('getTopListManufacturer')->willReturn(['name' => 'Intamin', 'nb' => 3]);
        $this->ridden->method('countRiddenInTop100Cohort')->willReturn(5);
        $this->ridden->method('findUserSuperlativeByMetric')->willReturn(null);
        $this->ridden->method('findUserCoasterByOpeningDate')->willReturn(null);
        $this->ridden->method('getUserAverageRating')->willReturn(3.8);
        $this->ridden->method('getUserRatingDistribution')->willReturn([]);
        $this->ridden->method('getMostRiddenByVocabulary')->willReturn(null);

        $this->parks->method('countForUser')->willReturn(30);
        $this->countries->method('countForUser')->willReturn(8);
        $this->coasters->method('findTop100CohortBounds')->willReturn(['size' => 100, 'cutoffRank' => 105]);
    }
}

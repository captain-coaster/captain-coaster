<?php

declare(strict_types=1);

namespace App\Tests\Service\Profile;

use App\Entity\User;
use App\Repository\CountryRepository;
use App\Repository\ParkRepository;
use App\Repository\RiddenCoasterRepository;
use App\Service\Profile\MemberFigures;
use App\Service\VocabularyLabeler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MemberFiguresTest extends TestCase
{
    private RiddenCoasterRepository&MockObject $riddenCoasterRepository;
    private ParkRepository&MockObject $parkRepository;
    private CountryRepository&MockObject $countryRepository;
    private VocabularyLabeler&MockObject $labeler;
    private MemberFigures $figures;

    protected function setUp(): void
    {
        $this->riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $this->parkRepository = $this->createMock(ParkRepository::class);
        $this->countryRepository = $this->createMock(CountryRepository::class);
        $this->labeler = $this->createMock(VocabularyLabeler::class);
        $this->figures = new MemberFigures($this->riddenCoasterRepository, $this->parkRepository, $this->countryRepository, $this->labeler);
    }

    public function testEmptyForAMemberWhoHasRiddenNothing(): void
    {
        $this->riddenCoasterRepository->method('countForUser')->willReturn(0);
        $this->riddenCoasterRepository->expects($this->never())->method('findMostRiddenCountry');
        $this->parkRepository->expects($this->never())->method('countForUser');

        $this->assertSame([], $this->figures->get(new User()));
    }

    public function testGathersTheFigures(): void
    {
        $user = new User();
        $this->riddenCoasterRepository->method('countForUser')->willReturn(214);
        $this->parkRepository->method('countForUser')->willReturn(38);
        $this->countryRepository->method('countForUser')->willReturn(9);
        $this->riddenCoasterRepository->method('findMostRiddenCountry')->willReturn(['code' => 'DE', 'name' => 'Germany', 'nb' => 61]);
        $this->riddenCoasterRepository->method('countTop100ForUser')->willReturn(['nb_top100' => 27, 'nb_top100_operating' => 24]);
        $this->riddenCoasterRepository->method('getMostRiddenManufacturer')->willReturn(['name' => 'Intamin', 'nb' => 33]);
        // The favorite manufacturer comes from the main Top's first 10 positions.
        $this->riddenCoasterRepository->expects($this->once())->method('getTopListManufacturer')->with($user, 10)->willReturn(['name' => 'RMC', 'nb' => 4]);
        $this->labeler->expects($this->once())->method('country')->with('DE', 'Germany')->willReturn('Allemagne');

        $this->assertSame([
            'nb_coasters' => 214,
            'nb_park' => 38,
            'nb_country' => 9,
            'country' => ['name' => 'Allemagne', 'nb' => 61],
            'top_100' => 27,
            'top_100_operating' => 24,
            'manufacturer' => ['name' => 'Intamin', 'nb' => 33],
            'top_rated_manufacturer' => ['name' => 'RMC', 'nb' => 4],
        ], $this->figures->get($user));
    }
}

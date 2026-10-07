<?php

declare(strict_types=1);

namespace App\Tests\Service\Home;

use App\Repository\RiddenCoasterRepository;
use App\Repository\UserRepository;
use App\Service\Home\CommunityFigures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CommunityFiguresTest extends TestCase
{
    public function testCountsOnceThenServesTheCache(): void
    {
        $riddenCoasterRepository = $this->createMock(RiddenCoasterRepository::class);
        $riddenCoasterRepository->expects($this->once())->method('countAll')->willReturn(1200);
        $riddenCoasterRepository->expects($this->once())->method('countNew')->willReturn(15);
        $riddenCoasterRepository->expects($this->once())->method('countReviews')->willReturn(300);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())->method('countAll')->willReturn(80);

        $figures = new CommunityFigures($riddenCoasterRepository, $userRepository, new ArrayAdapter());
        $expected = ['ratings' => 1200, 'newRatings' => 15, 'reviews' => 300, 'riders' => 80];

        $this->assertSame($expected, $figures->get());
        $this->assertSame($expected, $figures->get());
    }
}

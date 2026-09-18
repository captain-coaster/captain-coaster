<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ReprocessImagesCommand;
use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Service\ImageModerationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ReprocessImagesCommandTest extends TestCase
{
    private ImageRepository&MockObject $imageRepository;
    private CoasterRepository&MockObject $coasterRepository;
    private ImageModerationService&MockObject $imageModerationService;
    private EntityManagerInterface&MockObject $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->imageModerationService = $this->createMock(ImageModerationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $command = new ReprocessImagesCommand(
            $this->imageRepository,
            $this->coasterRepository,
            $this->imageModerationService,
            $this->entityManager
        );

        $this->commandTester = new CommandTester($command);
    }

    private function createImage(int $id): Image
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $image = new Image();
        $image->setCoaster($coaster);
        $image->setFilename("image-{$id}.jpg");
        $image->setWatermarked(false);

        $reflection = new \ReflectionProperty(Image::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($image, $id);

        return $image;
    }

    private function createCoaster(int $id, ?Image $mainImage): Coaster
    {
        $coaster = new Coaster();
        $coaster->setName("Coaster {$id}");
        $coaster->setMainImage($mainImage);

        $reflection = new \ReflectionProperty(Coaster::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($coaster, $id);

        return $coaster;
    }

    /** @return array{categories: string[], focalX: float, focalY: float, confidence: ?string, explanation: ?string} */
    private function cleanResult(): array
    {
        return ['categories' => [], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => null, 'explanation' => null];
    }

    public function testDefaultModeProcessesUnanalyzedImages(): void
    {
        $image = $this->createImage(1);

        $this->imageRepository->expects($this->once())
            ->method('findUnanalyzed')
            ->with(200)
            ->willReturn([$image]);

        $this->imageModerationService->expects($this->once())->method('analyze')->with($image)->willReturn($this->cleanResult());
        $this->imageModerationService->expects($this->once())->method('applyResult');

        $this->commandTester->execute([]);

        $this->assertStringContainsString('Processed 1 image(s), 0 flagged, 0 failed.', $this->commandTester->getDisplay());
    }

    public function testIdsOptionForcesReanalysisOfSpecificImages(): void
    {
        $image = $this->createImage(123);

        $this->imageRepository->expects($this->once())->method('find')->with(123)->willReturn($image);
        $this->imageRepository->expects($this->never())->method('findUnanalyzed');

        $this->imageModerationService->method('analyze')->willReturn($this->cleanResult());

        $this->commandTester->execute(['--ids' => '123']);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testCoasterIdsOptionTargetsEachCoastersMainImage(): void
    {
        $image = $this->createImage(5);
        $coaster = $this->createCoaster(10, $image);

        $this->coasterRepository->expects($this->once())->method('find')->with(10)->willReturn($coaster);
        $this->imageModerationService->expects($this->once())->method('analyze')->with($image)->willReturn($this->cleanResult());

        $this->commandTester->execute(['--coaster-ids' => '10']);
    }

    public function testHeroOptionAssemblesTheWholeCandidatePool(): void
    {
        $upcomingImage = $this->createImage(1);
        $trendingImage = $this->createImage(2);
        $featuredImage = $this->createImage(3);

        $this->coasterRepository->method('findUpcomingCoaster')->willReturn($this->createCoaster(1, $upcomingImage));
        $this->coasterRepository->method('findRecentlyOpenedCoaster')->willReturn(null);
        $this->coasterRepository->method('findTrendingCoaster')->willReturn($this->createCoaster(2, $trendingImage));
        $this->imageRepository->method('findFeaturedImage')->willReturn($featuredImage);

        $this->imageModerationService->expects($this->exactly(3))->method('analyze')->willReturn($this->cleanResult());

        $this->commandTester->execute(['--hero' => true]);
    }

    public function testAllMainImagesOptionTargetsEveryCoastersMainImage(): void
    {
        $image = $this->createImage(7);
        $coasterWithImage = $this->createCoaster(1, $image);
        $coasterWithoutImage = $this->createCoaster(2, null);

        $this->coasterRepository->method('findAll')->willReturn([$coasterWithImage, $coasterWithoutImage]);
        $this->imageModerationService->expects($this->once())->method('analyze')->with($image)->willReturn($this->cleanResult());

        $this->commandTester->execute(['--all-main-images' => true]);
    }

    public function testDryRunNeverCallsTheModelOrPersists(): void
    {
        $image = $this->createImage(1);
        $this->imageRepository->method('findUnanalyzed')->willReturn([$image]);

        $this->imageModerationService->expects($this->never())->method('analyze');
        $this->imageModerationService->expects($this->never())->method('applyResult');
        $this->entityManager->expects($this->never())->method('flush');

        $this->commandTester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('Image #1 (coaster: Test Coaster)', $this->commandTester->getDisplay());
    }

    public function testOneFailingImageDoesNotAbortTheRest(): void
    {
        $good = $this->createImage(1);
        $bad = $this->createImage(2);

        $this->imageRepository->method('findUnanalyzed')->willReturn([$bad, $good]);

        $this->imageModerationService->method('analyze')->willReturnCallback(
            fn (Image $image) => 2 === $image->getId() ? throw new \RuntimeException('boom') : $this->cleanResult()
        );

        $this->commandTester->execute([]);

        $this->assertStringContainsString('Processed 1 image(s), 0 flagged, 1 failed.', $this->commandTester->getDisplay());
    }
}

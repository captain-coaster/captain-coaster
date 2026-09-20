<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ReprocessImagesCommand;
use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Service\ImageManager;
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
    private ImageManager&MockObject $imageManager;
    private EntityManagerInterface&MockObject $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->imageModerationService = $this->createMock(ImageModerationService::class);
        $this->imageManager = $this->createMock(ImageManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $command = new ReprocessImagesCommand(
            $this->imageRepository,
            $this->coasterRepository,
            $this->imageModerationService,
            $this->imageManager,
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

    public function testProcessedImageHasItsResizedVariantsPurged(): void
    {
        $image = $this->createImage(1);
        $this->imageRepository->method('findUnanalyzed')->willReturn([$image]);
        $this->imageModerationService->method('analyze')->willReturn($this->cleanResult());

        $this->imageManager->expects($this->once())->method('removeCache')->with($image);

        $this->commandTester->execute([]);
    }

    public function testFailedAnalysisLeavesTheResizedVariantsAlone(): void
    {
        $this->imageRepository->method('findUnanalyzed')->willReturn([$this->createImage(1)]);
        $this->imageModerationService->method('analyze')->willReturn(null);

        $this->imageManager->expects($this->never())->method('removeCache');

        $this->commandTester->execute([]);
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

    public function testHeroOptionAssemblesCoasterMainImagesAndUnanalyzedPhotos(): void
    {
        $upcomingImage = $this->createImage(1);
        $trendingImage = $this->createImage(2);
        $unanalyzedPhoto = $this->createImage(3);

        $this->coasterRepository->method('findUpcomingCoasterIds')->willReturn([1]);
        $this->coasterRepository->method('findRecentlyOpenedCoasterIds')->willReturn([]);
        $this->coasterRepository->method('findTrendingCoasterIds')->willReturn([2]);
        $this->coasterRepository->expects($this->once())->method('findBy')->with(['id' => [1, 2]])
            ->willReturn([$this->createCoaster(1, $upcomingImage), $this->createCoaster(2, $trendingImage)]);

        $this->imageRepository->expects($this->once())->method('findFeaturedImageIds')->willReturn([3, 4]);
        $this->imageRepository->expects($this->once())->method('findBy')
            ->with(['id' => [3, 4], 'analyzedAt' => null], ['id' => 'ASC'], 200)
            ->willReturn([$unanalyzedPhoto]);

        $this->imageModerationService->expects($this->exactly(3))->method('analyze')->willReturn($this->cleanResult());

        $this->commandTester->execute(['--hero' => true]);
    }

    public function testHeroOptionLimitCapsThePhotosOnly(): void
    {
        $this->coasterRepository->method('findUpcomingCoasterIds')->willReturn([]);
        $this->coasterRepository->method('findRecentlyOpenedCoasterIds')->willReturn([]);
        $this->coasterRepository->method('findTrendingCoasterIds')->willReturn([]);
        $this->coasterRepository->method('findBy')->willReturn([]);

        $this->imageRepository->method('findFeaturedImageIds')->willReturn([3]);
        $this->imageRepository->expects($this->once())->method('findBy')
            ->with($this->anything(), ['id' => 'ASC'], 5)
            ->willReturn([]);

        $this->commandTester->execute(['--hero' => true, '--limit' => '5']);
    }

    public function testHeroOptionDedupesAnImageReachableTwice(): void
    {
        $sharedImage = $this->createImage(9);

        $this->coasterRepository->method('findUpcomingCoasterIds')->willReturn([1]);
        $this->coasterRepository->method('findRecentlyOpenedCoasterIds')->willReturn([]);
        $this->coasterRepository->method('findTrendingCoasterIds')->willReturn([]);
        $this->coasterRepository->method('findBy')->willReturn([$this->createCoaster(1, $sharedImage)]);
        $this->imageRepository->method('findFeaturedImageIds')->willReturn([9]);
        $this->imageRepository->method('findBy')->willReturn([$sharedImage]);

        $this->imageModerationService->expects($this->once())->method('analyze')->with($sharedImage)->willReturn($this->cleanResult());

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
        $this->imageManager->expects($this->never())->method('removeCache');
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

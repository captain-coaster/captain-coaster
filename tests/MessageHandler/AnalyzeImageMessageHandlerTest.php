<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Message\AnalyzeImageMessage;
use App\MessageHandler\AnalyzeImageMessageHandler;
use App\Repository\ImageRepository;
use App\Service\ImageModerationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AnalyzeImageMessageHandlerTest extends TestCase
{
    private function createImage(int $id = 1): Image
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $image = new Image();
        $image->setCoaster($coaster);
        $image->setFilename('test-coaster-abc123.jpg');
        $image->setWatermarked(false);

        $reflection = new \ReflectionClass($image);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($image, $id);

        return $image;
    }

    public function testIsANoOpWhenTheImageIsGone(): void
    {
        $imageRepository = $this->createMock(ImageRepository::class);
        $imageRepository->method('find')->with(42)->willReturn(null);

        $imageModerationService = $this->createMock(ImageModerationService::class);
        $imageModerationService->expects($this->never())->method('analyze');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $handler = new AnalyzeImageMessageHandler($imageRepository, $imageModerationService, $entityManager);
        $handler(new AnalyzeImageMessage(42));

        $this->addToAssertionCount(1);
    }

    public function testAppliesTheResultAndFlushesOnSuccess(): void
    {
        $image = $this->createImage();

        $imageRepository = $this->createMock(ImageRepository::class);
        $imageRepository->method('find')->with(1)->willReturn($image);

        $result = ['categories' => [], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => null, 'explanation' => null];

        $imageModerationService = $this->createMock(ImageModerationService::class);
        $imageModerationService->method('analyze')->with($image)->willReturn($result);
        $imageModerationService->expects($this->once())->method('applyResult')->with($image, $result);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $handler = new AnalyzeImageMessageHandler($imageRepository, $imageModerationService, $entityManager);
        $handler(new AnalyzeImageMessage(1));
    }

    public function testThrowsSoMessengerRetriesWhenAnalysisFails(): void
    {
        $image = $this->createImage();

        $imageRepository = $this->createMock(ImageRepository::class);
        $imageRepository->method('find')->with(1)->willReturn($image);

        $imageModerationService = $this->createMock(ImageModerationService::class);
        $imageModerationService->method('analyze')->willReturn(null);
        $imageModerationService->expects($this->never())->method('applyResult');

        $handler = new AnalyzeImageMessageHandler($imageRepository, $imageModerationService, $this->createMock(EntityManagerInterface::class));

        $this->expectException(\RuntimeException::class);
        $handler(new AnalyzeImageMessage(1));
    }
}

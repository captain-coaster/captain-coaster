<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\ImageReport;
use App\Repository\ImageReportRepository;
use App\Service\BedrockService;
use App\Service\ImageManager;
use App\Service\ImageModerationService;
use App\Service\PictureUrlSigner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ImageModerationServiceTest extends TestCase
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

    /** @param array{success: bool, content?: string, error?: string, metadata: array<string, mixed>} $bedrockResponse */
    private function createService(
        array $bedrockResponse,
        ?LoggerInterface $logger = null,
        ?ImageReportRepository $imageReportRepository = null,
        ?string $httpContent = 'fake-jpeg-bytes',
        ?EntityManagerInterface $entityManager = null,
    ): ImageModerationService {
        $bedrockService = $this->createMock(BedrockService::class);
        $bedrockService->method('invokeVisionModel')->willReturn($bedrockResponse);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($httpContent ?? '');

        $httpClient = $this->createMock(HttpClientInterface::class);
        if (null === $httpContent) {
            $httpClient->method('request')->willThrowException(new \RuntimeException('connection failed'));
        } else {
            $httpClient->method('request')->willReturn($response);
        }

        $pictureUrlSigner = $this->createMock(PictureUrlSigner::class);
        $pictureUrlSigner->method('sign')->willReturn('https://pictures.captaincoaster.com/1440x1440/jpg/test-coaster-abc123.jpg?s=xyz');

        return new ImageModerationService(
            $bedrockService,
            $httpClient,
            $pictureUrlSigner,
            $this->createMock(ImageManager::class),
            $imageReportRepository ?? $this->createMock(ImageReportRepository::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }

    public function testAnalyzeReturnsParsedResultOnSuccess(): void
    {
        $service = $this->createService([
            'success' => true,
            'content' => '{"categories":["watermark"],"focal_x":0.5,"focal_y":0.3,"confidence":"high","explanation":"Logo overlay in corner."}',
            'metadata' => [],
        ]);

        $result = $service->analyze($this->createImage());

        $this->assertSame([
            'categories' => ['watermark'],
            'focalX' => 0.5,
            'focalY' => 0.3,
            'confidence' => 'high',
            'explanation' => 'Logo overlay in corner.',
        ], $result);
    }

    public function testAnalyzeReturnsEmptyCategoriesForACleanPhoto(): void
    {
        $service = $this->createService([
            'success' => true,
            'content' => '{"categories":[],"focal_x":0.5,"focal_y":0.5,"confidence":"high","explanation":null}',
            'metadata' => [],
        ]);

        $result = $service->analyze($this->createImage());

        $this->assertSame([], $result['categories']);
        $this->assertNull($result['explanation']);
    }

    public function testAnalyzeReturnsNullAndLogsWhenLightboxFetchFails(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Image moderation could not fetch the lightbox derivative');

        $service = $this->createService(['success' => true, 'metadata' => []], $logger, null, null);

        $this->assertNull($service->analyze($this->createImage()));
    }

    public function testAnalyzeReturnsNullAndLogsOnBedrockFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Image moderation Bedrock call failed');

        $service = $this->createService(['success' => false, 'error' => 'Throttled', 'metadata' => []], $logger);

        $this->assertNull($service->analyze($this->createImage()));
    }

    public function testAnalyzeReturnsNullAndLogsOnUnparsableResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('Image moderation response could not be parsed');

        $service = $this->createService(['success' => true, 'content' => 'not json', 'metadata' => []], $logger);

        $this->assertNull($service->analyze($this->createImage()));
    }

    public function testAnalyzeRecognizesOnridePhotoCategory(): void
    {
        $service = $this->createService([
            'success' => true,
            'content' => '{"categories":["onride_photo"],"focal_x":0.5,"focal_y":0.4,"confidence":"high","explanation":"Commercial on-ride photo with a price overlay."}',
            'metadata' => [],
        ]);

        $result = $service->analyze($this->createImage());

        $this->assertSame(['onride_photo'], $result['categories']);
    }

    public function testAnalyzeDiscardsUnknownCategoriesButKeepsValidOnes(): void
    {
        $service = $this->createService([
            'success' => true,
            'content' => '{"categories":["watermark","not-a-real-category"],"focal_x":0.5,"focal_y":0.5,"confidence":"low","explanation":"x"}',
            'metadata' => [],
        ]);

        $result = $service->analyze($this->createImage());

        $this->assertSame(['watermark'], $result['categories']);
    }

    public function testAnalyzeClampsOutOfRangeFocalCoordinates(): void
    {
        $service = $this->createService([
            'success' => true,
            'content' => '{"categories":[],"focal_x":1.4,"focal_y":-0.2,"confidence":"low","explanation":null}',
            'metadata' => [],
        ]);

        $result = $service->analyze($this->createImage());

        $this->assertSame(1.0, $result['focalX']);
        $this->assertSame(0.0, $result['focalY']);
    }

    public function testApplyResultEnablesTheImageWhenNoCategoriesAreFlagged(): void
    {
        $service = $this->createService(['success' => true, 'metadata' => []]);
        $image = $this->createImage();

        $service->applyResult($image, ['categories' => [], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => null, 'explanation' => null]);

        $this->assertTrue($image->isEnabled());
        $this->assertSame(0.5, $image->getFocalX());
        $this->assertNotNull($image->getAnalyzedAt());
    }

    public function testApplyResultLeavesTheImageDisabledWhenFlagged(): void
    {
        $service = $this->createService(['success' => true, 'metadata' => []]);
        $image = $this->createImage();

        $service->applyResult($image, ['categories' => [ImageReport::CATEGORY_WATERMARK], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'high', 'explanation' => 'x']);

        $this->assertFalse($image->isEnabled());
    }

    public function testApplyResultSkipsCreatingADuplicateReport(): void
    {
        $imageReportRepository = $this->createMock(ImageReportRepository::class);
        $imageReportRepository->method('hasUnresolvedReport')->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $service = $this->createService(['success' => true, 'metadata' => []], null, $imageReportRepository, entityManager: $entityManager);
        $image = $this->createImage();

        $service->applyResult($image, ['categories' => [ImageReport::CATEGORY_OFFTOPIC], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'high', 'explanation' => 'x']);

        $this->assertFalse($image->isEnabled());
    }
}

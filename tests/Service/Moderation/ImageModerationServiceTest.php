<?php

declare(strict_types=1);

namespace App\Tests\Service\Moderation;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\ImageReport;
use App\Repository\ImageReportRepository;
use App\Service\Ai\AiCallFailed;
use App\Service\Ai\AiClient;
use App\Service\Ai\AiTask;
use App\Service\ImageManager;
use App\Service\Moderation\ImageModerationService;
use App\Service\PictureUrlSigner;
use App\Tests\AiStub;
use App\Tests\EntityId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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

        return EntityId::set($image, $id);
    }

    /** @param array<string, mixed>|AiCallFailed $aiAnswer the answer's data, or the failure the call throws */
    private function createService(
        array|AiCallFailed $aiAnswer,
        ?LoggerInterface $logger = null,
        ?ImageReportRepository $imageReportRepository = null,
        ?string $httpContent = 'fake-jpeg-bytes',
        ?EntityManagerInterface $entityManager = null,
        ?ImageManager $imageManager = null,
    ): ImageModerationService {
        $aiClient = $this->createMock(AiClient::class);
        if ($aiAnswer instanceof AiCallFailed) {
            $aiClient->method('run')->willThrowException($aiAnswer);
        } else {
            $aiClient->method('run')->willReturn(AiStub::result($aiAnswer));
        }

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($httpContent ?? '');

        $httpClient = $this->createMock(HttpClientInterface::class);
        if (null === $httpContent) {
            $httpClient->method('request')->willThrowException(new \RuntimeException('connection failed'));
        } else {
            $httpClient->method('request')->willReturn($response);
        }

        $pictureUrlSigner = $this->createMock(PictureUrlSigner::class);
        $pictureUrlSigner->method('signImage')->willReturn('https://pictures.captaincoaster.com/1440x1440/jpg/test-coaster-abc123.jpg?s=xyz');

        return new ImageModerationService(
            $aiClient,
            $httpClient,
            $pictureUrlSigner,
            $imageManager ?? $this->createMock(ImageManager::class),
            $imageReportRepository ?? $this->createMock(ImageReportRepository::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }

    public function testAnalyzeReturnsParsedResultOnSuccess(): void
    {
        $service = $this->createService(['categories' => ['retouched'], 'focal_x' => 0.5, 'focal_y' => 0.3, 'confidence' => 'high', 'explanation' => 'Heavy filter applied.']);

        $result = $service->analyze($this->createImage());

        $this->assertSame([
            'categories' => ['retouched'],
            'focalX' => 0.5,
            'focalY' => 0.3,
            'confidence' => 'high',
            'explanation' => 'Heavy filter applied.',
        ], $result);
    }

    public function testAnalyzeReturnsEmptyCategoriesForACleanPhoto(): void
    {
        $service = $this->createService(['categories' => [], 'focal_x' => 0.5, 'focal_y' => 0.5, 'confidence' => 'high', 'explanation' => null]);

        $result = $service->analyze($this->createImage());

        $this->assertSame([], $result['categories']);
        $this->assertNull($result['explanation']);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function nonHighConfidenceProvider(): iterable
    {
        yield 'medium' => [['confidence' => 'medium'], 'medium'];
        yield 'low' => [['confidence' => 'low'], 'low'];
    }

    /** @param array<string, string> $confidence */
    #[DataProvider('nonHighConfidenceProvider')]
    public function testAnalyzeFlagsANoIssueVerdictThatIsNotHighlyConfident(array $confidence, string $expectedLabel): void
    {
        $service = $this->createService(['categories' => [], 'focal_x' => 0.5, 'focal_y' => 0.5, ...$confidence, 'explanation' => null]);

        $result = $service->analyze($this->createImage());

        $this->assertSame([ImageReport::CATEGORY_UNCERTAIN], $result['categories']);
        $this->assertSame(\sprintf('No issue flagged, but the model was not highly confident (%s).', $expectedLabel), $result['explanation']);
    }

    public function testAnalyzeKeepsTheModelsOwnFlagWhenConfidenceIsNotHigh(): void
    {
        $service = $this->createService(['categories' => ['offtopic'], 'focal_x' => 0.5, 'focal_y' => 0.5, 'confidence' => 'medium', 'explanation' => 'Not a coaster.']);

        $result = $service->analyze($this->createImage());

        $this->assertSame([ImageReport::CATEGORY_OFFTOPIC], $result['categories']);
        $this->assertSame('Not a coaster.', $result['explanation']);
    }

    public function testAFailedFetchIsThrownSoMessengerRetries(): void
    {
        $service = $this->createService([], httpContent: null);

        $this->expectExceptionMessage('Could not fetch image #1 for moderation: connection failed');
        $service->analyze($this->createImage());
    }

    public function testAFailedCallIsThrownSoMessengerRetries(): void
    {
        $service = $this->createService(AiStub::failure(AiTask::ImageModeration));

        $this->expectException(AiCallFailed::class);
        $service->analyze($this->createImage());
    }

    public function testAnalyzeRecognizesOnridePhotoCategory(): void
    {
        $service = $this->createService(['categories' => ['onride_photo'], 'focal_x' => 0.5, 'focal_y' => 0.4, 'confidence' => 'high', 'explanation' => 'Commercial on-ride photo with a price overlay.']);

        $result = $service->analyze($this->createImage());

        $this->assertSame(['onride_photo'], $result['categories']);
    }

    public function testApplyResultLeavesTheImageUntouchedWhenTheS3WriteFails(): void
    {
        $imageManager = $this->createMock(ImageManager::class);
        $imageManager->method('syncOriginalMetadata')->willThrowException(new \RuntimeException('S3 down'));
        $service = $this->createService([], imageManager: $imageManager);
        $image = $this->createImage();
        $image->setFocalX(0.2);
        $image->setFocalY(0.3);

        try {
            $service->applyResult($image, ['categories' => [], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'high', 'explanation' => null]);
            $this->fail('The S3 failure must propagate.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0.2, $image->getFocalX());
        $this->assertSame(0.3, $image->getFocalY());
        $this->assertNull($image->getAnalyzedAt());
    }

    public function testApplyResultEnablesTheImageWhenNoCategoriesAreFlagged(): void
    {
        $service = $this->createService([]);
        $image = $this->createImage();

        $service->applyResult($image, ['categories' => [], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'high', 'explanation' => null]);

        $this->assertTrue($image->isEnabled());
        $this->assertSame(0.5, $image->getFocalX());
        $this->assertNotNull($image->getAnalyzedAt());
    }

    public function testApplyResultLeavesTheImageDisabledWhenFlagged(): void
    {
        $service = $this->createService([]);
        $image = $this->createImage();

        $service->applyResult($image, ['categories' => [ImageReport::CATEGORY_RETOUCHED], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'high', 'explanation' => 'x']);

        $this->assertFalse($image->isEnabled());
    }

    public function testApplyResultTakesDownAnAlreadyPublishedImageWhenReflagged(): void
    {
        $service = $this->createService([]);
        $image = $this->createImage();
        $image->setEnabled(true);

        $service->applyResult($image, ['categories' => [ImageReport::CATEGORY_PEOPLE_SUBJECT], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'high', 'explanation' => 'x']);

        $this->assertFalse($image->isEnabled());
    }

    public function testApplyResultReportsAnUncertainVerdictAndTakesTheImageDown(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->callback(
            static fn (ImageReport $report): bool => [ImageReport::CATEGORY_UNCERTAIN] === $report->getCategories()
                && 'medium' === $report->getAiConfidence()
        ));

        $service = $this->createService([], entityManager: $entityManager);
        $image = $this->createImage();
        $image->setEnabled(true);

        $service->applyResult($image, ['categories' => [ImageReport::CATEGORY_UNCERTAIN], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'medium', 'explanation' => 'x']);

        $this->assertFalse($image->isEnabled());
    }

    public function testApplyResultSkipsCreatingADuplicateReport(): void
    {
        $imageReportRepository = $this->createMock(ImageReportRepository::class);
        $imageReportRepository->method('hasUnresolvedReport')->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $service = $this->createService([], null, $imageReportRepository, entityManager: $entityManager);
        $image = $this->createImage();

        $service->applyResult($image, ['categories' => [ImageReport::CATEGORY_OFFTOPIC], 'focalX' => 0.5, 'focalY' => 0.5, 'confidence' => 'high', 'explanation' => 'x']);

        $this->assertFalse($image->isEnabled());
    }
}

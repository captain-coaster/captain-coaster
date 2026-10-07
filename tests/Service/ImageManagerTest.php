<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Image;
use App\Message\AnalyzeImageMessage;
use App\Repository\ImageRepository;
use App\Service\ImageManager;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Uses a real S3Client wired to Aws\MockHandler -- S3Client's operations (copyObject) are
 * magic/dynamic, generated from the API definition at runtime, so PHPUnit's createMock() can't
 * stub them directly. MockHandler is the SDK's own supported way to intercept the HTTP layer.
 */
class ImageManagerTest extends TestCase
{
    private function makeImageManager(MockHandler $mockHandler, ?FilesystemOperator $variants = null): ImageManager
    {
        $s3Client = new S3Client([
            'region' => 'eu-west-3',
            'version' => '2006-03-01',
            'credentials' => false,
            'handler' => $mockHandler,
        ]);

        return new ImageManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FilesystemOperator::class),
            $s3Client,
            $this->createMock(ImageRepository::class),
            'captain-pictures-original',
            $variants ?? $this->createMock(FilesystemOperator::class),
            $this->createMock(MessageBusInterface::class),
        );
    }

    public function testUploadWritesTheOriginalUnderItsId(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getContent')->willReturn('jpeg-bytes');
        $image = new Image();
        $image->setFile($file);
        $image->setWatermarked(true);
        new \ReflectionProperty(Image::class, 'id')->setValue($image, 48500);

        $originals = $this->createMock(FilesystemOperator::class);
        $originals->expects($this->once())->method('write')->with(
            '48500.jpg',
            'jpeg-bytes',
            ['Metadata' => ['watermark' => '1'], 'ContentType' => 'image/jpeg'],
        );

        $manager = new ImageManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            $originals,
            new S3Client(['region' => 'eu-west-3', 'version' => '2006-03-01', 'credentials' => false, 'handler' => new MockHandler()]),
            $this->createMock(ImageRepository::class),
            'captain-pictures-original',
            $this->createMock(FilesystemOperator::class),
            $this->createMock(MessageBusInterface::class),
        );

        $this->assertSame('48500.jpg', $manager->upload($image));
    }

    public function testStoreWritesTheOriginalUnderItsIdThenDispatchesAnalysis(): void
    {
        [$image, $em] = $this->newUploadAndEntityManager();
        $em->expects($this->exactly(2))->method('flush');

        $originals = $this->createMock(FilesystemOperator::class);
        $originals->expects($this->once())->method('write')->with('48500.jpg', $this->anything(), $this->anything());

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (AnalyzeImageMessage $message) => 48500 === $message->imageId))
            ->willReturn(new Envelope(new AnalyzeImageMessage(48500)));

        $this->makeStoringImageManager($em, $originals, $bus)->store($image);

        $this->assertSame('48500.jpg', $image->getFilename());
        $this->assertSame(hash('sha256', 'jpeg-bytes'), $image->getHash());
    }

    public function testIsDuplicateLooksUpTheSha256(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, 'jpeg-bytes');
        $file = $this->createMock(UploadedFile::class);
        $file->method('getPathname')->willReturn($path);

        $existing = new Image();
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['hash' => hash('sha256', 'jpeg-bytes')])
            ->willReturn($existing);

        $manager = new ImageManager(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(FilesystemOperator::class),
            new S3Client(['region' => 'eu-west-3', 'version' => '2006-03-01', 'credentials' => false, 'handler' => new MockHandler()]),
            $repository,
            'captain-pictures-original',
            $this->createMock(FilesystemOperator::class),
            $this->createMock(MessageBusInterface::class),
        );

        $this->assertSame($existing, $manager->isDuplicate($file));
    }

    // Inside wrapInTransaction(): the exception rolls the row back, and nothing is dispatched
    // for an image that won't exist.
    public function testStorePropagatesAFailedWriteWithoutDispatching(): void
    {
        [$image, $em] = $this->newUploadAndEntityManager();

        $originals = $this->createMock(FilesystemOperator::class);
        $originals->method('write')->willThrowException(new \RuntimeException('S3 down'));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->makeStoringImageManager($em, $originals, $bus)->store($image);
    }

    /** @return array{Image, EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject} */
    private function newUploadAndEntityManager(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, 'jpeg-bytes');
        $file = $this->createMock(UploadedFile::class);
        $file->method('getPathname')->willReturn($path);
        $file->method('getContent')->willReturn('jpeg-bytes');

        $image = new Image();
        $image->setFile($file);
        $image->setWatermarked(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $func) => $func($em));
        // The INSERT gives the row its id.
        $em->method('persist')->willReturnCallback(static function (Image $image): void {
            new \ReflectionProperty(Image::class, 'id')->setValue($image, 48500);
        });

        return [$image, $em];
    }

    private function makeStoringImageManager(EntityManagerInterface $em, FilesystemOperator $originals, MessageBusInterface $bus): ImageManager
    {
        return new ImageManager(
            $em,
            $this->createMock(LoggerInterface::class),
            $originals,
            new S3Client(['region' => 'eu-west-3', 'version' => '2006-03-01', 'credentials' => false, 'handler' => new MockHandler()]),
            $this->createMock(ImageRepository::class),
            'captain-pictures-original',
            $this->createMock(FilesystemOperator::class),
            $bus,
        );
    }

    public function testRemoveVariantsDeletesTheImagePrefixInTheVariantsBucket(): void
    {
        $variants = $this->createMock(FilesystemOperator::class);
        $variants->expects($this->once())->method('deleteDirectory')->with('i/42');

        $image = new Image();
        new \ReflectionProperty(Image::class, 'id')->setValue($image, 42);

        $this->makeImageManager(new MockHandler(), $variants)->removeVariants($image);
    }

    public function testSyncOriginalMetadataCopiesObjectInPlaceWithReplacedMetadata(): void
    {
        $mockHandler = new MockHandler();

        $copyObjectCommand = null;
        $mockHandler->append(function (CommandInterface $command) use (&$copyObjectCommand) {
            $copyObjectCommand = $command;

            return new Result([]);
        });

        $imageManager = $this->makeImageManager($mockHandler);

        $image = $this->createMock(Image::class);
        $image->method('getFilename')->willReturn('holiday-world-cannonball-6a752ddaebc05.jpg');
        $image->method('isWatermarked')->willReturn(true);
        $image->method('getFocalX')->willReturn(0.42);
        $image->method('getFocalY')->willReturn(0.73);

        $imageManager->syncOriginalMetadata($image);

        self::assertNotNull($copyObjectCommand, 'CopyObject was never called.');
        self::assertSame('captain-pictures-original', $copyObjectCommand['Bucket']);
        self::assertSame('holiday-world-cannonball-6a752ddaebc05.jpg', $copyObjectCommand['Key']);
        self::assertSame('REPLACE', $copyObjectCommand['MetadataDirective']);
        // REPLACE also resets Content-Type to binary/octet-stream unless re-supplied.
        self::assertSame(Image::MIME_TYPE, $copyObjectCommand['ContentType']);
        // The watermark flag must be re-supplied here too -- REPLACE overwrites all metadata,
        // it doesn't merge, so omitting it would silently drop the existing value.
        self::assertSame([
            'watermark' => '1',
            'focal-x' => '0.42',
            'focal-y' => '0.73',
        ], $copyObjectCommand['Metadata']);
    }

    public function testSyncOriginalMetadataKeepsTheRev(): void
    {
        $mockHandler = new MockHandler();

        $copyObjectCommand = null;
        $mockHandler->append(function (CommandInterface $command) use (&$copyObjectCommand) {
            $copyObjectCommand = $command;

            return new Result([]);
        });

        $image = new Image();
        $image->setFilename('42.jpg');
        $image->setWatermarked(false);
        $image->setFocalX(0.42);
        $image->setFocalY(0.73);
        $image->setRev(3);

        $this->makeImageManager($mockHandler)->syncOriginalMetadata($image);

        self::assertSame([
            'watermark' => '0',
            'focal-x' => '0.42',
            'focal-y' => '0.73',
            'rev' => '3',
        ], $copyObjectCommand['Metadata'] ?? null);
    }

    public function testOriginalMetadataIsTheWholeSetAndOmitsWhatIsUnset(): void
    {
        $image = new Image();
        $image->setWatermarked(true);

        // The exact keys captain-infra's image-resizer reads; a new one belongs here and there.
        self::assertSame(['watermark' => '1'], ImageManager::originalMetadata($image));

        $image->setFocalX(0.42);
        self::assertSame(['watermark' => '1'], ImageManager::originalMetadata($image), 'half a focal point is none');

        $image->setFocalY(0.73);
        $image->setRev(2);
        self::assertSame(
            ['watermark' => '1', 'focal-x' => '0.42', 'focal-y' => '0.73', 'rev' => '2'],
            ImageManager::originalMetadata($image)
        );
    }
}

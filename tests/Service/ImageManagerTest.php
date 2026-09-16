<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\ImageManager;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Exercises removeCache() against a real S3Client wired to Aws\MockHandler -- S3Client's
 * operations (getPaginator, deleteObjects) are magic/dynamic, generated from the API
 * definition at runtime, so PHPUnit's createMock() can't stub them directly. MockHandler is
 * the SDK's own supported way to intercept the HTTP layer instead.
 */
class ImageManagerTest extends TestCase
{
    public function testRemoveCacheDeletesJpgAndAvifAcrossEveryDiscoveredSize(): void
    {
        $mockHandler = new MockHandler();

        // ListObjectsV2 (via the Delimiter paginator) reporting two "sizes" already cached.
        $mockHandler->append(new Result([
            'CommonPrefixes' => [
                ['Prefix' => '96x72/'],
                ['Prefix' => '280x210/'],
            ],
            'IsTruncated' => false,
        ]));

        // DeleteObjects -- captured instead of just stubbed, so the assertion is on what
        // removeCache() actually asked S3 to delete.
        $deleteObjectsCommand = null;
        $mockHandler->append(function (CommandInterface $command) use (&$deleteObjectsCommand) {
            $deleteObjectsCommand = $command;

            return new Result(['Deleted' => []]);
        });

        $imageManager = $this->makeImageManager($mockHandler);

        $image = $this->createMock(Image::class);
        $image->method('getFilename')->willReturn('holiday-world-cannonball-6a752ddaebc05.jpg');

        $imageManager->removeCache($image);

        self::assertNotNull($deleteObjectsCommand, 'DeleteObjects was never called.');
        $keys = array_map(
            static fn (array $object) => $object['Key'],
            $deleteObjectsCommand['Delete']['Objects']
        );

        // The full point of this test: exactly jpg + avif per size, no webp (dropped -- see
        // ImageManager::CACHED_FORMATS), no size that wasn't actually discovered. Destination
        // extension matches the encoded format, not the source filename's own (always .jpg).
        self::assertEqualsCanonicalizing([
            '96x72/jpg/holiday-world-cannonball-6a752ddaebc05.jpg',
            '96x72/avif/holiday-world-cannonball-6a752ddaebc05.avif',
            '280x210/jpg/holiday-world-cannonball-6a752ddaebc05.jpg',
            '280x210/avif/holiday-world-cannonball-6a752ddaebc05.avif',
        ], $keys);
    }

    public function testRemoveCacheIsANoOpWhenNoSizeHasEverBeenCachedYet(): void
    {
        $mockHandler = new MockHandler();
        $mockHandler->append(new Result(['IsTruncated' => false]));

        $imageManager = $this->makeImageManager($mockHandler);

        $image = $this->createMock(Image::class);
        $image->method('getFilename')->willReturn('brand-new-upload-6a752ddaebc05.jpg');

        // If removeCache() called DeleteObjects here, the mock queue (exhausted after the
        // single ListObjectsV2 response above) would throw -- that's the actual assertion,
        // not just an absence of exceptions being incidental.
        $imageManager->removeCache($image);

        $this->addToAssertionCount(1);
    }

    private function makeImageManager(MockHandler $mockHandler): ImageManager
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
            'captain-pictures-resized'
        );
    }
}

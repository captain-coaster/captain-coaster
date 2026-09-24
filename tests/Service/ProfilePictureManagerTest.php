<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProfilePictureManager;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProfilePictureManagerTest extends TestCase
{
    public function testDeleteRemovesOriginalLegacyCopyAndV2Variants(): void
    {
        $original = $this->createMock(FilesystemOperator::class);
        $original->expects($this->once())->method('delete')->with('pp_9_67cd55be84931.png');
        $cache = $this->createMock(FilesystemOperator::class);
        $cache->expects($this->once())->method('delete')->with('pp_9_67cd55be84931.png');
        $variants = $this->createMock(FilesystemOperator::class);
        $variants->expects($this->once())->method('deleteDirectory')->with('a/9_67cd55be84931');

        new ProfilePictureManager($this->createMock(LoggerInterface::class), $original, $cache, $variants)
            ->deleteProfilePicture('pp_9_67cd55be84931.png');
    }

    public function testDeleteSkipsVariantsForAFilenameWithoutARef(): void
    {
        $variants = $this->createMock(FilesystemOperator::class);
        $variants->expects($this->never())->method('deleteDirectory');

        new ProfilePictureManager(
            $this->createMock(LoggerInterface::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(FilesystemOperator::class),
            $variants,
        )->deleteProfilePicture('pp__67cd55be84931.jpg');
    }
}

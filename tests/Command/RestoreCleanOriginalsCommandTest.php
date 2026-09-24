<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RestoreCleanOriginalsCommand;
use App\Entity\Image;
use App\Repository\ImageRepository;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** Same S3Client + Aws\MockHandler approach as RenameImagesCommandTest. */
class RestoreCleanOriginalsCommandTest extends TestCase
{
    private const string UUID = '3308d7ee-14ca-4c1b-a827-04cd5daf03ee';

    private ImageRepository&MockObject $imageRepository;
    private FilesystemOperator&MockObject $filesystem;
    private MockHandler $s3Handler;
    private string $dir;

    protected function setUp(): void
    {
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->imageRepository->method('findWatermarkBakedIn')->willReturn([]);
        $this->filesystem = $this->createMock(FilesystemOperator::class);
        $this->s3Handler = new MockHandler();

        $this->dir = sys_get_temp_dir().'/restore-originals-test-'.uniqid();
        mkdir($this->dir.'/backup', 0o777, true);
        mkdir($this->dir.'/report');

        $gd = imagecreatetruecolor(4, 4);
        imagejpeg($gd, $this->dir.'/backup/'.self::UUID.'.jpeg');

        $image = new Image();
        $image->setFilename(self::UUID.'.jpeg');
        $image->setCreatedAt(new \DateTime('2022-05-01'));
        new \ReflectionProperty(Image::class, 'id')->setValue($image, 42);
        $this->imageRepository->method('findOneByUuid')->with(self::UUID)->willReturn($image);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*/*') ?: []);
        @rmdir($this->dir.'/backup');
        @rmdir($this->dir.'/report');
        @rmdir($this->dir);
    }

    private function runCommand(bool $execute): CommandTester
    {
        $s3Client = new S3Client([
            'region' => 'eu-west-3',
            'version' => '2006-03-01',
            'credentials' => false,
            'handler' => $this->s3Handler,
        ]);

        $tester = new CommandTester(new RestoreCleanOriginalsCommand(
            $this->imageRepository,
            $this->filesystem,
            $s3Client,
            'captain-pictures-original',
        ));
        $tester->execute(array_filter([
            '--backup-dir' => [$this->dir.'/backup'],
            '--report-dir' => $this->dir.'/report',
            '--execute' => $execute,
        ]));

        return $tester;
    }

    private function headResult(int $size): Result
    {
        return new Result([
            'ContentLength' => $size,
            'ContentType' => 'image/jpeg',
            'Metadata' => ['focal-x' => '0.52', 'focal-y' => '0.57', 'watermark' => '1'],
        ]);
    }

    public function testExecuteCarriesAllMetadataAndWritesIntelligentTiering(): void
    {
        $copyParams = null;
        $this->s3Handler->append($this->headResult(10));
        $this->s3Handler->append(function (CommandInterface $cmd) use (&$copyParams): Result {
            $copyParams = $cmd->toArray();

            return new Result([]);
        });

        $this->filesystem->expects($this->once())->method('write')->with(
            self::UUID.'.jpeg',
            $this->anything(),
            [
                'Metadata' => ['focal-x' => '0.52', 'focal-y' => '0.57', 'watermark' => '1'],
                'ContentType' => 'image/jpeg',
                'StorageClass' => 'INTELLIGENT_TIERING',
            ],
        );

        $tester = $this->runCommand(execute: true);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('restored-originals-backup/'.self::UUID.'.jpeg', $copyParams['Key'] ?? null);
        $this->assertSame('INTELLIGENT_TIERING', $copyParams['StorageClass'] ?? null);
    }

    public function testDryRunWritesNothing(): void
    {
        $this->s3Handler->append($this->headResult(10));

        $this->filesystem->expects($this->never())->method('write');

        $tester = $this->runCommand(execute: false);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(0, $this->s3Handler->count());
        $this->assertStringContainsString('would_restore', (string) file_get_contents($this->dir.'/report/restored.csv'));
    }

    public function testSkipsWhenS3IsAlreadyLarger(): void
    {
        $this->s3Handler->append($this->headResult(10_000_000));

        $this->filesystem->expects($this->never())->method('write');

        $this->runCommand(execute: true);

        $this->assertStringContainsString('s3_already_larger_or_equal', (string) file_get_contents($this->dir.'/report/skipped.csv'));
    }
}

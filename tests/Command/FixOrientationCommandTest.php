<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\FixOrientationCommand;
use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\HeroService;
use App\Service\ImageManager;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Same S3Client + Aws\MockHandler approach as RenameImagesCommandTest.
 */
class FixOrientationCommandTest extends TestCase
{
    private const string OLD_KEY = '3308d7ee-14ca-4c1b-a827-04cd5daf03ee.jpeg';

    private ImageRepository&MockObject $imageRepository;
    private Connection&MockObject $connection;
    private ImageManager&MockObject $imageManager;
    private HeroService&MockObject $heroService;
    private MockHandler $s3Handler;
    private Image $image;
    private string $file;

    protected function setUp(): void
    {
        $this->image = new Image();
        $this->image->setFilename('42.jpg');
        $this->image->setWatermarked(false);
        new \ReflectionProperty(Image::class, 'id')->setValue($this->image, 42);

        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->imageRepository->method('find')->with(42)->willReturn($this->image);
        $this->connection = $this->createMock(Connection::class);
        $this->imageManager = $this->createMock(ImageManager::class);
        $this->heroService = $this->createMock(HeroService::class);
        $this->s3Handler = new MockHandler();

        $this->file = sys_get_temp_dir().'/fix-orientation-test-'.uniqid().'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private static function jpeg(int $width, int $height): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($width, $height));

        return (string) ob_get_clean();
    }

    private function runCommand(int $orientation, bool $execute): CommandTester
    {
        file_put_contents($this->file, \sprintf("42,%s,%d\n", self::OLD_KEY, $orientation));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($this->connection);

        $tester = new CommandTester(new FixOrientationCommand(
            $this->imageRepository,
            $entityManager,
            $this->imageManager,
            $this->heroService,
            new S3Client(['region' => 'eu-west-3', 'version' => '2006-03-01', 'credentials' => false, 'handler' => $this->s3Handler]),
            'captain-pictures-original',
            'captain-pictures-resized',
        ));
        $tester->execute(array_filter(['--file' => $this->file, '--execute' => $execute]));

        return $tester;
    }

    /** Backup HEAD, original GET, backup GET: what every image reads before deciding. */
    private function appendReads(string $original, string $backup, string $restoredAt = '2026-09-24T19:45:45+00:00'): void
    {
        $this->s3Handler->append(new Result(['LastModified' => new \DateTimeImmutable($restoredAt)]));
        $this->s3Handler->append(new Result(['Body' => $original, 'ContentType' => 'image/jpeg', 'Metadata' => ['focal-x' => '0.56', 'focal-y' => '0.46', 'watermark' => '1']]));
        $this->s3Handler->append(new Result(['Body' => $backup]));
    }

    public function testAddsTheOrientationWithoutTouchingTheImageData(): void
    {
        $jpeg = self::jpeg(8, 6);
        $fixed = FixOrientationCommand::withOrientation($jpeg, 6);

        $this->assertSame(36, \strlen($fixed) - \strlen($jpeg));
        $this->assertSame(substr($jpeg, 0, 20), substr($fixed, 0, 20), 'SOI and JFIF APP0 stay first');
        $this->assertSame(substr($jpeg, 20), substr($fixed, 56));
        $this->assertTrue(FixOrientationCommand::hasExif($fixed));
        $this->assertFalse(FixOrientationCommand::hasExif($jpeg));

        $path = $this->file.'.jpg';
        file_put_contents($path, $fixed);
        $exif = exif_read_data($path);
        unlink($path);
        $this->assertSame(6, $exif['Orientation'] ?? null);
    }

    /** @return iterable<string, array{int, array{float, float}}> */
    public static function focalTurns(): iterable
    {
        // A spot near the top-left corner of the stored pixels.
        yield 'half turn' => [3, [0.9, 0.8]];
        yield 'quarter turn clockwise' => [6, [0.8, 0.1]];
        yield 'quarter turn counter-clockwise' => [8, [0.2, 0.9]];
    }

    /** @param array{float, float} $expected */
    #[DataProvider('focalTurns')]
    public function testFocalFollowsTheRotation(int $orientation, array $expected): void
    {
        $this->assertSame($expected, FixOrientationCommand::turnFocal(0.1, 0.2, $orientation));
    }

    public function testDryRunWritesNothing(): void
    {
        $this->image->setFocalX(0.56);
        $this->image->setFocalY(0.46);
        $this->appendReads(self::jpeg(8, 6), self::jpeg(4, 3));

        $this->connection->expects($this->never())->method('update');
        $this->imageManager->expects($this->never())->method('removeVariants');

        $tester = $this->runCommand(3, false);

        $this->assertStringContainsString('would fix', $tester->getDisplay());
        $this->assertSame(0, $this->s3Handler->count());
    }

    public function testFocalDetectedBeforeTheRestoreIsKeptAndRevBumped(): void
    {
        $this->image->setWatermarked(true);
        $this->image->setFocalX(0.56);
        $this->image->setFocalY(0.46);
        $this->image->setAnalyzedAt(new \DateTime('2026-09-20T10:00:00+00:00'));
        $this->appendReads(self::jpeg(8, 6), self::jpeg(4, 3));

        $put = null;
        $this->s3Handler->append(static function (CommandInterface $cmd) use (&$put): Result {
            $put = $cmd->toArray();

            return new Result([]);
        });
        $this->connection->expects($this->once())->method('update')->with(
            'image',
            $this->callback(static fn (array $row): bool => ['hash', 'rev'] === array_keys($row) && 1 === $row['rev'] && 64 === \strlen($row['hash'])),
            ['id' => 42],
        );
        $this->imageManager->expects($this->once())->method('removeVariants');
        $this->heroService->expects($this->once())->method('invalidate');

        $this->runCommand(3, true);

        $this->assertSame('42.jpg', $put['Key']);
        $this->assertSame(['watermark' => '1', 'focal-x' => '0.56', 'focal-y' => '0.46', 'rev' => '1'], $put['Metadata']);
        $this->assertSame('INTELLIGENT_TIERING', $put['StorageClass']);
        $this->assertTrue(FixOrientationCommand::hasExif((string) $put['Body']));
    }

    public function testFocalDetectedOnTheTurnedImageIsTurned(): void
    {
        $this->image->setFocalX(0.1);
        $this->image->setFocalY(0.2);
        $this->image->setAnalyzedAt(new \DateTime('2026-10-03T10:00:00+00:00'));
        $this->appendReads(self::jpeg(8, 6), self::jpeg(3, 4));
        $this->s3Handler->append(new Result([]));

        $this->connection->expects($this->once())->method('update')->with(
            'image',
            $this->callback(static fn (array $row): bool => 0.8 === $row['focal_x'] && 0.1 === $row['focal_y'] && 1 === $row['rev']),
            ['id' => 42],
        );

        $this->runCommand(6, true);
    }

    public function testFocalAnalysedOnALegacyVariantOlderThanTheRestoreIsKept(): void
    {
        $this->image->setFocalX(0.56);
        $this->image->setFocalY(0.46);
        $this->image->setAnalyzedAt(new \DateTime('2026-09-30T18:53:00+00:00'));
        $this->appendReads(self::jpeg(8, 6), self::jpeg(4, 3));
        // HEAD of the legacy 1440 jpg variant: generated before the restore, so upright.
        $this->s3Handler->append(new Result(['LastModified' => new \DateTimeImmutable('2026-09-24T13:33:38+00:00')]));

        $tester = $this->runCommand(3, false);

        $this->assertStringContainsString('kept', $tester->getDisplay());
        $this->assertStringNotContainsString('turned', $tester->getDisplay());
    }

    public function testFocalAnalysedAfterTheRestoreWithoutAnOlderLegacyVariantIsTurned(): void
    {
        $this->image->setFocalX(0.56);
        $this->image->setFocalY(0.46);
        $this->image->setAnalyzedAt(new \DateTime('2026-09-30T18:53:00+00:00'));
        $this->appendReads(self::jpeg(8, 6), self::jpeg(4, 3));
        $this->s3Handler->append(static fn (CommandInterface $cmd) => throw new S3Exception('Not Found', $cmd, ['response' => new Response(404)]));

        $tester = $this->runCommand(3, false);

        $this->assertStringContainsString('turned 0.56,0.46 -> 0.44,0.54', $tester->getDisplay());
    }

    public function testWithoutFocalTheNextRevAloneChangesTheUrl(): void
    {
        $this->image->setRev(2);
        $this->appendReads(self::jpeg(8, 6), self::jpeg(4, 3));
        $put = null;
        $this->s3Handler->append(static function (CommandInterface $cmd) use (&$put): Result {
            $put = $cmd->toArray();

            return new Result([]);
        });

        $this->connection->expects($this->once())->method('update')->with(
            'image',
            $this->callback(static fn (array $row): bool => ['hash', 'rev'] === array_keys($row) && 3 === $row['rev']),
            ['id' => 42],
        );

        $tester = $this->runCommand(3, true);

        $this->assertSame(['watermark' => '0', 'rev' => '3'], $put['Metadata']);
        $this->assertStringContainsString('new URL: rev 3', $tester->getDisplay());
    }

    public function testSkipsWhenTheBackupDimensionsContradictTheOrientation(): void
    {
        $this->appendReads(self::jpeg(8, 6), self::jpeg(4, 3));

        $tester = $this->runCommand(6, true);

        $this->assertStringContainsString('contradicts the backup dimensions', $tester->getDisplay());
    }

    public function testSkipsAnOriginalThatAlreadyHasExif(): void
    {
        $this->appendReads(FixOrientationCommand::withOrientation(self::jpeg(8, 6), 3), self::jpeg(4, 3));

        $tester = $this->runCommand(3, true);

        $this->assertStringContainsString('already has EXIF', $tester->getDisplay());
    }
}

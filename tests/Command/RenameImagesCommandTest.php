<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RenameImagesCommand;
use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\HeroService;
use App\Service\ImageManager;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * S3Client's own operations (headObject, copyObject, deleteObjects) are magic/dynamic -- built
 * from the API definition at runtime -- so PHPUnit's createMock() can't stub them directly
 * (same constraint as ImageManagerTest). A real S3Client wired to Aws\MockHandler is used
 * instead; ImageManager itself (a plain service) is mocked as a whole, so its own S3 calls
 * (inside writeFocalPointMetadata()) never touch the queue.
 */
class RenameImagesCommandTest extends TestCase
{
    private ImageRepository&MockObject $imageRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ImageManager&MockObject $imageManager;
    private HeroService&MockObject $heroService;
    private MockHandler $s3Handler;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->imageManager = $this->createMock(ImageManager::class);
        $this->heroService = $this->createMock(HeroService::class);
        $this->s3Handler = new MockHandler();

        $this->projectDir = sys_get_temp_dir().'/rename-images-test-'.uniqid();
        mkdir($this->projectDir.'/var', 0o777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->projectDir.'/var/*') ?: []);
        @rmdir($this->projectDir.'/var');
        @rmdir($this->projectDir);
    }

    private function makeCommandTester(): CommandTester
    {
        $s3Client = new S3Client([
            'region' => 'eu-west-3',
            'version' => '2006-03-01',
            'credentials' => false,
            'handler' => $this->s3Handler,
        ]);

        $command = new RenameImagesCommand(
            $this->imageRepository,
            $this->entityManager,
            $this->imageManager,
            $this->heroService,
            $s3Client,
            'captain-pictures-original',
            $this->projectDir,
        );

        return new CommandTester($command);
    }

    private function createImage(int $id, string $filename, ?float $focalX = null, ?float $focalY = null, bool $watermarked = false): Image
    {
        $image = new Image();
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');
        $image->setCoaster($coaster);
        $image->setFilename($filename);
        $image->setWatermarked($watermarked);
        $image->setFocalX($focalX);
        $image->setFocalY($focalY);

        // Public reflection access needs no setAccessible() since PHP 8.1 (deprecated in 8.5).
        new \ReflectionProperty(Image::class, 'id')->setValue($image, $id);

        return $image;
    }

    /** @param array<string, mixed> $metadata */
    private function headObjectResult(array $metadata = []): Result
    {
        return new Result(['Metadata' => $metadata]);
    }

    /** @return Query<mixed, mixed>&MockObject */
    private function queryMock(): Query&MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();

        return $query;
    }

    // SymfonyStyle wraps long lines (e.g. inside the success/note blocks), which can split a
    // phrase this test asserts on across two lines -- collapse all whitespace so assertions
    // don't depend on where the terminal width happened to wrap.
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    // -------------------------------------------------------------------
    // No targets
    // -------------------------------------------------------------------

    public function testNoImagesIsASuccessWithNoWork(): void
    {
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([]);

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No images to examine.', $this->display($tester));
    }

    public function testLimitAndAfterIdAreForwardedToTheRepository(): void
    {
        $this->imageRepository->expects($this->once())
            ->method('findPhotosOrderedById')
            ->with(2000, 500)
            ->willReturn([]);

        $tester = $this->makeCommandTester();
        $tester->execute(['--after-id' => '2000', '--limit' => '500']);
    }

    // -------------------------------------------------------------------
    // --dry-run: audits, never writes
    // -------------------------------------------------------------------

    public function testDryRunReportsWhatWouldBeRenamedAndNeverWrites(): void
    {
        $image = $this->createImage(1, 'coaster-slug-abc123.jpg');
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult());

        $this->entityManager->expects($this->never())->method('flush');
        $this->imageManager->expects($this->never())->method('writeFocalPointMetadata');

        $tester = $this->makeCommandTester();
        $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 would be renamed', $this->display($tester));
        self::assertStringContainsString('0 metadata mismatch', $this->display($tester));
    }

    public function testDryRunDoesNotCountAnAlreadyRenamedImageAsWouldRename(): void
    {
        $image = $this->createImage(42, '42.jpg');
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult());

        $tester = $this->makeCommandTester();
        $tester->execute(['--dry-run' => true]);

        self::assertStringContainsString('0 would be renamed', $this->display($tester));
    }

    public function testDryRunReportsAFocalPointMismatchAndFailsTheCommand(): void
    {
        $image = $this->createImage(1, 'coaster-slug-abc123.jpg', focalX: 0.42, focalY: 0.73);
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult(['focal-x' => '0.99', 'focal-y' => '0.73']));

        $tester = $this->makeCommandTester();
        $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('focal-x mismatch -- DB "0.42" vs S3 "0.99"', $this->display($tester));
        self::assertStringContainsString('1 metadata mismatch', $this->display($tester));
    }

    public function testDryRunTreatsMissingS3FocalMetadataTheSameAsNoFocalPointInTheDb(): void
    {
        // Neither side has a focal point -- '-' on both, not a mismatch.
        $image = $this->createImage(1, 'coaster-slug-abc123.jpg', focalX: null, focalY: null);
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult());

        $tester = $this->makeCommandTester();
        $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('0 metadata mismatch', $this->display($tester));
    }

    public function testDryRunReportsAnUnreadableOriginalWithoutAborting(): void
    {
        $bad = $this->createImage(1, 'missing.jpg');
        $good = $this->createImage(2, 'coaster-slug-abc123.jpg');
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$bad, $good]);

        $this->s3Handler->append(static function (CommandInterface $command): void {
            throw new S3Exception('not found', $command, ['code' => 'NoSuchKey']);
        });
        $this->s3Handler->append($this->headObjectResult());

        $tester = $this->makeCommandTester();
        $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('1 unreadable original', $this->display($tester));
        self::assertStringContainsString('Examined 2 image(s)', $this->display($tester));
    }

    public function testFixMetadataIsIgnoredAndNotedWithDryRun(): void
    {
        $image = $this->createImage(1, 'coaster-slug-abc123.jpg');
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult());

        $this->imageManager->expects($this->never())->method('writeFocalPointMetadata');

        $tester = $this->makeCommandTester();
        $tester->execute(['--dry-run' => true, '--fix-metadata' => true]);

        self::assertStringContainsString('--fix-metadata is ignored with --dry-run', $this->display($tester));
    }

    // -------------------------------------------------------------------
    // Real run
    // -------------------------------------------------------------------

    public function testRenamesACleanImageCopiesUpdatesTheDbAndInvalidatesTheHeroCache(): void
    {
        $image = $this->createImage(48213, 'voltron-europa-park-abc123.jpg');
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult());

        $copyCommand = null;
        $this->s3Handler->append(static function (CommandInterface $command) use (&$copyCommand) {
            $copyCommand = $command;

            return new Result([]);
        });

        $query = $this->queryMock();
        $query->expects($this->once())->method('execute');
        $this->entityManager->expects($this->once())->method('createQuery')->willReturn($query);
        $this->entityManager->expects($this->once())->method('flush');
        $this->heroService->expects($this->once())->method('invalidate');

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('captain-pictures-original', $copyCommand['Bucket']);
        self::assertSame('48213.jpg', $copyCommand['Key']);
        self::assertSame('captain-pictures-original/voltron-europa-park-abc123.jpg', rawurldecode($copyCommand['CopySource']));
        self::assertSame('COPY', $copyCommand['MetadataDirective']);
        self::assertSame('INTELLIGENT_TIERING', $copyCommand['StorageClass']);
        self::assertSame('48213.jpg', $image->getFilename());

        $logged = file_get_contents($this->projectDir.'/var/rename-images.log');
        self::assertStringContainsString("48213\tvoltron-europa-park-abc123.jpg\t48213.jpg\n", (string) $logged);
        self::assertStringContainsString('Renamed 1 image(s)', $this->display($tester));
    }

    public function testAlreadyRenamedImageIsSkippedWithNoS3OrDbCalls(): void
    {
        $image = $this->createImage(48213, '48213.jpg');
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        // Nothing appended to the S3 mock handler -- a call would throw "queue is empty".

        $this->entityManager->expects($this->never())->method('flush');
        $this->heroService->expects($this->never())->method('invalidate');

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 already done', $this->display($tester));
    }

    public function testAMismatchWithoutFixMetadataIsSkippedNotRenamed(): void
    {
        $image = $this->createImage(1, 'coaster-slug-abc123.jpg', focalX: 0.42, focalY: 0.73);
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult(['focal-x' => '0.99', 'focal-y' => '0.73']));
        // No second S3 response queued -- copyObject must never be called.

        $this->imageManager->expects($this->never())->method('writeFocalPointMetadata');
        $this->entityManager->expects($this->never())->method('flush');

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('1 skipped (metadata mismatch)', $this->display($tester));
        self::assertSame('coaster-slug-abc123.jpg', $image->getFilename(), 'filename must be untouched');
    }

    public function testFixMetadataRewritesThenRenamesAMismatchedImage(): void
    {
        $image = $this->createImage(1, 'coaster-slug-abc123.jpg', focalX: 0.42, focalY: 0.73);
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$image]);
        $this->s3Handler->append($this->headObjectResult(['focal-x' => '0.99', 'focal-y' => '0.73']));
        $this->s3Handler->append(new Result([])); // copyObject for the rename itself

        $this->imageManager->expects($this->once())->method('writeFocalPointMetadata')->with($image);
        $query = $this->queryMock();
        $this->entityManager->method('createQuery')->willReturn($query);
        $this->entityManager->expects($this->once())->method('flush');

        $tester = $this->makeCommandTester();
        $tester->execute(['--fix-metadata' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Renamed 1 image(s)', $this->display($tester));
        self::assertSame('1.jpg', $image->getFilename());
    }

    public function testACopyFailureIsSkippedAndDoesNotAbortTheRun(): void
    {
        $bad = $this->createImage(1, 'coaster-slug-abc123.jpg');
        $good = $this->createImage(2, 'other-slug-def456.jpg');
        $this->imageRepository->method('findPhotosOrderedById')->willReturn([$bad, $good]);

        $this->s3Handler->append($this->headObjectResult()); // audit for #1
        $this->s3Handler->append(static function (CommandInterface $command): void {
            throw new S3Exception('throttled', $command, ['code' => 'SlowDown']);
        }); // copyObject for #1 fails
        $this->s3Handler->append($this->headObjectResult()); // audit for #2
        $this->s3Handler->append(new Result([])); // copyObject for #2 succeeds

        $query = $this->queryMock();
        $this->entityManager->method('createQuery')->willReturn($query);
        $this->entityManager->expects($this->once())->method('flush'); // only image #2

        $tester = $this->makeCommandTester();
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $this->display($tester);
        self::assertStringContainsString('Renamed 1 image(s)', $display);
        self::assertStringContainsString('1 failed', $display);
        self::assertSame('coaster-slug-abc123.jpg', $bad->getFilename(), 'a failed copy must not touch the DB');
        self::assertSame('2.jpg', $good->getFilename());
    }

    // -------------------------------------------------------------------
    // --purge-old
    // -------------------------------------------------------------------

    public function testPurgeOldWithNoLogFileIsANoOp(): void
    {
        $tester = $this->makeCommandTester();
        $tester->execute(['--purge-old' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No rename log found', $this->display($tester));
    }

    public function testPurgeOldDryRunListsKeysWithoutDeleting(): void
    {
        file_put_contents($this->projectDir.'/var/rename-images.log', "1\told-a.jpg\t1.jpg\n2\told-b.jpg\t2.jpg\n");
        // Nothing queued on the S3 handler -- a delete call would throw.

        $tester = $this->makeCommandTester();
        $tester->execute(['--purge-old' => true, '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $this->display($tester);
        self::assertStringContainsString('Would delete 2 old key(s)', $display);
        self::assertStringContainsString('old-a.jpg', $display);
        self::assertStringContainsString('old-b.jpg', $display);
    }

    public function testPurgeOldDeletesEveryDistinctOldKeyFromTheLog(): void
    {
        file_put_contents(
            $this->projectDir.'/var/rename-images.log',
            "1\told-a.jpg\t1.jpg\n2\told-b.jpg\t2.jpg\n1\told-a.jpg\t1.jpg\n" // a re-run duplicate
        );

        $deleteCommand = null;
        $this->s3Handler->append(static function (CommandInterface $command) use (&$deleteCommand) {
            $deleteCommand = $command;

            return new Result(['Deleted' => []]);
        });

        $tester = $this->makeCommandTester();
        $tester->execute(['--purge-old' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $keys = array_map(static fn (array $o) => $o['Key'], $deleteCommand['Delete']['Objects']);
        self::assertEqualsCanonicalizing(['old-a.jpg', 'old-b.jpg'], $keys);
        self::assertStringContainsString('Deleted 2 old key(s)', $this->display($tester));
    }
}

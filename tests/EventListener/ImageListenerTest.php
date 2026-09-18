<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Coaster;
use App\Entity\Image;
use App\EventListener\ImageListener;
use App\Message\AnalyzeImageMessage;
use App\Service\HeroService;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ImageListenerTest extends TestCase
{
    private ImageManager&MockObject $imageManager;
    private HeroService&MockObject $heroService;
    private MessageBusInterface&MockObject $messageBus;
    private ImageListener $listener;

    protected function setUp(): void
    {
        $this->imageManager = $this->createMock(ImageManager::class);
        $this->heroService = $this->createMock(HeroService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->listener = new ImageListener($this->imageManager, $this->heroService, $this->messageBus);
    }

    private function createImage(int $id, bool $withUploadedFile): Image
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $image = new Image();
        $image->setCoaster($coaster);
        $image->setFilename('test-coaster-abc123.jpg');
        $image->setWatermarked(false);

        if ($withUploadedFile) {
            $image->setFile($this->createMock(UploadedFile::class));
        }

        $reflection = new \ReflectionClass($image);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($image, $id);

        return $image;
    }

    public function testPostPersistDispatchesAnalysisForANewUpload(): void
    {
        $image = $this->createImage(1, withUploadedFile: true);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (AnalyzeImageMessage $message) => 1 === $message->imageId))
            ->willReturn(new Envelope(new AnalyzeImageMessage(1)));

        $this->listener->postPersist($image, new PostPersistEventArgs($image, $this->createMock(EntityManagerInterface::class)));
    }

    // Defensive, same guard as prePersist(): $file is a transient property, so any future
    // code path that persists a new Image without going through the upload form (a fixture,
    // duplicating an existing photo's S3 object) must not try to analyze a file that never
    // existed.
    public function testPostPersistDoesNotDispatchWhenThereWasNoUploadedFile(): void
    {
        $image = $this->createImage(1, withUploadedFile: false);

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->listener->postPersist($image, new PostPersistEventArgs($image, $this->createMock(EntityManagerInterface::class)));
    }
}

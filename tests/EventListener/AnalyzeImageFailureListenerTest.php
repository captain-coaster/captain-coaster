<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Entity\ImageReport;
use App\EventListener\AnalyzeImageFailureListener;
use App\Message\AnalyzeImageMessage;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

class AnalyzeImageFailureListenerTest extends TestCase
{
    private ImageRepository&MockObject $imageRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private AnalyzeImageFailureListener $listener;

    protected function setUp(): void
    {
        $this->imageRepository = $this->createMock(ImageRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->listener = new AnalyzeImageFailureListener($this->imageRepository, $this->entityManager);
    }

    private function createImage(int $id): Image
    {
        $coaster = new Coaster();
        $coaster->setName('Test Coaster');

        $image = new Image();
        $image->setCoaster($coaster);
        $image->setFilename('test-coaster-abc123.jpg');
        $image->setWatermarked(false);

        $reflection = new \ReflectionProperty(Image::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($image, $id);

        return $image;
    }

    public function testDoesNothingWhileRetriesRemain(): void
    {
        $event = new WorkerMessageFailedEvent(new Envelope(new AnalyzeImageMessage(1)), 'async', new \RuntimeException('boom'));
        $event->setForRetry();

        $this->imageRepository->expects($this->never())->method('find');
        $this->entityManager->expects($this->never())->method('persist');

        $this->listener->__invoke($event);
    }

    public function testIgnoresMessagesThatArentAnalyzeImageMessage(): void
    {
        $event = new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async', new \RuntimeException('boom'));

        $this->imageRepository->expects($this->never())->method('find');

        $this->listener->__invoke($event);
    }

    public function testDoesNothingWhenTheImageIsGone(): void
    {
        $this->imageRepository->method('find')->with(1)->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');

        $event = new WorkerMessageFailedEvent(new Envelope(new AnalyzeImageMessage(1)), 'async', new \RuntimeException('boom'));
        $this->listener->__invoke($event);
    }

    public function testCreatesAnAnalysisFailedReportOnFinalFailure(): void
    {
        $image = $this->createImage(1);
        $this->imageRepository->method('find')->with(1)->willReturn($image);

        $persisted = null;
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted): void { $persisted = $entity; });
        $this->entityManager->expects($this->once())->method('flush');

        $event = new WorkerMessageFailedEvent(new Envelope(new AnalyzeImageMessage(1)), 'async', new \RuntimeException('Bedrock unreachable'));
        $this->listener->__invoke($event);

        $this->assertInstanceOf(ImageReport::class, $persisted);
        $this->assertSame([ImageReport::CATEGORY_ANALYSIS_FAILED], $persisted->getCategories());
        $this->assertSame('Bedrock unreachable', $persisted->getAiExplanation());
    }
}

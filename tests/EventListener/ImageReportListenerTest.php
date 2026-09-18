<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\ImageReport;
use App\EventListener\ImageReportListener;
use App\Service\PictureUrlSigner;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\MessageInterface;

class ImageReportListenerTest extends TestCase
{
    private ChatterInterface&MockObject $chatter;
    private PictureUrlSigner&MockObject $pictureUrlSigner;
    private ImageReportListener $listener;

    protected function setUp(): void
    {
        $this->chatter = $this->createMock(ChatterInterface::class);
        $this->pictureUrlSigner = $this->createMock(PictureUrlSigner::class);
        $this->listener = new ImageReportListener($this->chatter, $this->pictureUrlSigner);
    }

    public function testPostPersistSendsADiscordNotification(): void
    {
        $imageReport = new ImageReport();
        $imageReport->setCoasterName('Test Coaster');
        $imageReport->setImageFilename('test-coaster-abc123.jpg');
        $imageReport->setCategories([ImageReport::CATEGORY_WATERMARK]);
        $imageReport->setAiExplanation('Logo overlay in corner.');

        $eventArgs = new PostPersistEventArgs($imageReport, $this->createMock(EntityManagerInterface::class));

        $this->chatter->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(MessageInterface::class));

        $this->listener->postPersist($imageReport, $eventArgs);
    }

    // No image -- e.g. AnalyzeImageFailureListener building a report for an image that has
    // since been deleted -- must not throw trying to sign a URL for it.
    public function testPostPersistDoesNotThrowWhenImageIsGone(): void
    {
        $imageReport = new ImageReport();
        $imageReport->setCoasterName('Test Coaster');
        $imageReport->setCategories([ImageReport::CATEGORY_ANALYSIS_FAILED]);

        $eventArgs = new PostPersistEventArgs($imageReport, $this->createMock(EntityManagerInterface::class));

        $this->pictureUrlSigner->expects($this->never())->method('sign');
        $this->chatter->expects($this->once())->method('send');

        $this->listener->postPersist($imageReport, $eventArgs);
    }
}

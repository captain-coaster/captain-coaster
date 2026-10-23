<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Image;
use App\EventListener\ImageListener;
use App\Service\HeroService;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageListenerTest extends TestCase
{
    /** @return iterable<string, array{bool, array<string, array{mixed, mixed}>, bool}> */
    public static function updates(): iterable
    {
        yield 'enabled by moderation' => [true, ['enabled' => [false, true]], true];
        yield 'disabled' => [false, ['enabled' => [true, false]], true];
        // updated_at breaks like-counter ties between enabled images
        yield 'credit of an enabled image' => [true, ['credit' => ['a', 'b']], true];
        yield 'like on an enabled image' => [true, ['likeCounter' => [3, 4]], true];
        yield 'filename of a fresh upload' => [false, ['filename' => ['', '12.jpg']], false];
        yield 'credit of a disabled image' => [false, ['credit' => ['a', 'b']], false];
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    #[DataProvider('updates')]
    public function testMainImagesAreRecomputedAfterTheFlushUnlessTheImageStaysDisabled(bool $enabled, array $changeSet, bool $expected): void
    {
        $image = new Image()->setEnabled($enabled);
        $em = $this->entityManager($changeSet);

        $imageManager = $this->createMock(ImageManager::class);
        $imageManager->expects($expected ? $this->once() : $this->never())->method('setMainImages');
        $heroService = $this->createMock(HeroService::class);
        $heroService->expects($expected ? $this->once() : $this->never())->method('invalidate');

        $listener = new ImageListener($imageManager, $heroService);
        $listener->postUpdate($image, new PostUpdateEventArgs($image, $em));
        $listener->postFlush(new PostFlushEventArgs($em));
    }

    public function testNothingIsRecomputedInsideTheFlush(): void
    {
        $image = new Image()->setEnabled(true);
        $em = $this->entityManager(['enabled' => [false, true]]);

        $imageManager = $this->createMock(ImageManager::class);
        $imageManager->expects($this->never())->method('setMainImages');

        new ImageListener($imageManager, $this->createStub(HeroService::class))->postUpdate($image, new PostUpdateEventArgs($image, $em));
    }

    public function testSeveralChangesInOneFlushRecomputeOnceAndTheNextFlushStartsClean(): void
    {
        $em = $this->entityManager([]);
        $imageManager = $this->createMock(ImageManager::class);
        $imageManager->expects($this->once())->method('setMainImages');

        $listener = new ImageListener($imageManager, $this->createStub(HeroService::class));
        $listener->postRemove(new Image(), new PostRemoveEventArgs(new Image(), $em));
        $listener->postRemove(new Image(), new PostRemoveEventArgs(new Image(), $em));
        $listener->postFlush(new PostFlushEventArgs($em));
        $listener->postFlush(new PostFlushEventArgs($em));
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    private function entityManager(array $changeSet): EntityManagerInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changeSet);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        return $em;
    }
}

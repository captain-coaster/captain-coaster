<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Image;
use App\EventListener\ImageListener;
use App\Service\HeroService;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
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
        // The upload's second flush: the statement deadlocked there
        yield 'filename of a fresh upload' => [false, ['filename' => ['', '12.jpg']], false];
        yield 'credit of a disabled image' => [false, ['credit' => ['a', 'b']], false];
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    #[DataProvider('updates')]
    public function testPostUpdateSetsMainImagesUnlessTheImageStaysDisabled(bool $enabled, array $changeSet, bool $expected): void
    {
        $image = (new Image())->setEnabled($enabled);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn($changeSet);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        $imageManager = $this->createMock(ImageManager::class);
        $imageManager->expects($expected ? $this->once() : $this->never())->method('setMainImages');
        $heroService = $this->createMock(HeroService::class);
        $heroService->expects($expected ? $this->once() : $this->never())->method('invalidate');

        (new ImageListener($imageManager, $heroService))->postUpdate($image, new PostUpdateEventArgs($image, $em));
    }
}

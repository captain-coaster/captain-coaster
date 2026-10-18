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
    /** @return iterable<string, array{array<string, array{mixed, mixed}>, bool}> */
    public static function changeSets(): iterable
    {
        yield 'enabled' => [['enabled' => [false, true]], true];
        yield 'like counter' => [['likeCounter' => [3, 4]], true];
        // The upload's second flush: the main image can't change, and the statement deadlocked there
        yield 'filename' => [['filename' => ['', '12.jpg']], false];
        yield 'credit' => [['credit' => ['a', 'b']], false];
    }

    /** @param array<string, array{mixed, mixed}> $changeSet */
    #[DataProvider('changeSets')]
    public function testPostUpdateSetsMainImagesOnlyWhenTheChoiceCanChange(array $changeSet, bool $expected): void
    {
        $image = new Image();

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

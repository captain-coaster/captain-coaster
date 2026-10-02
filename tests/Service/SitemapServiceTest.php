<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\Country;
use App\Entity\Image;
use App\Entity\Park;
use App\Service\PictureUrlSigner;
use App\Service\SitemapService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SitemapServiceTest extends TestCase
{
    /** @var EntityRepository<Image>&MockObject */
    private EntityRepository&MockObject $imageRepository;

    protected function setUp(): void
    {
        $this->imageRepository = $this->createMock(EntityRepository::class);
    }

    private function makeService(bool $v2): SitemapService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->imageRepository);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://captaincoaster.com/en/coasters/42/voltron');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new SitemapService(
            $em,
            $router,
            $translator,
            new PictureUrlSigner('https://pictures.example.com', 'test-secret', $v2),
            ['en', 'fr'],
        );
    }

    private function createImage(): Image
    {
        $country = new Country();
        $country->setName('country.germany');

        $park = new Park();
        $park->setName('Europa-Park');
        $park->setCountry($country);

        $coaster = new Coaster();
        $coaster->setName('Voltron');
        $coaster->setSlug('voltron-europa-park');
        $coaster->setPark($park);

        $image = new Image();
        $image->setCoaster($coaster);
        $image->setFilename('48213.jpg');
        $image->setWatermarked(true);

        new \ReflectionProperty(Image::class, 'id')->setValue($image, 48213);

        return $image;
    }

    public function testImageLocIsTheSignedV2LightboxUrl(): void
    {
        $this->imageRepository->method('findBy')->willReturn([$this->createImage()]);

        $urls = $this->makeService(true)->getUrlsForImages();

        self::assertCount(1, $urls);
        self::assertSame('https://captaincoaster.com/en/coasters/42/voltron', $urls[0]['loc']);
        self::assertMatchesRegularExpression(
            '#^https://pictures\.example\.com/i/48213/[0-9a-f]{6}/[0-9a-f]{6}/1440x1440/voltron-europa-park\.jpg$#',
            $urls[0]['images'][0]['loc'],
        );
        self::assertSame('Voltron', $urls[0]['images'][0]['title']);
        self::assertSame('Europa-Park, country.germany', $urls[0]['images'][0]['geo_location']);
    }

    public function testImageLocIsTheSignedLegacyUrlWhileV2IsOff(): void
    {
        $this->imageRepository->method('findBy')->willReturn([$this->createImage()]);

        $urls = $this->makeService(false)->getUrlsForImages();

        self::assertMatchesRegularExpression(
            '#^https://pictures\.example\.com/1440x1440/jpg/48213\.jpg\?s=[0-9a-f]{32}$#',
            $urls[0]['images'][0]['loc'],
        );
    }

    public function testOnlyPublishedWatermarkedPhotosAreListed(): void
    {
        $this->imageRepository->expects($this->once())
            ->method('findBy')
            ->with(['watermarked' => true, 'enabled' => true])
            ->willReturn([]);

        self::assertSame([], $this->makeService(true)->getUrlsForImages());
    }
}

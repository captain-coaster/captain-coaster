<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Coaster;
use App\Entity\Image;
use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Service\PictureUrlSigner;
use App\Service\SitemapService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapServiceTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private ImageRepository&MockObject $imageRepository;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->imageRepository = $this->createMock(ImageRepository::class);
    }

    private function makeService(bool $v2 = true): SitemapService
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params): string => 'https://captaincoaster.com/'.$params['_locale'].'/'.$route
                .(isset($params['id']) ? '/'.$params['id'].'/'.$params['slug'] : '')
        );

        return new SitemapService(
            $this->coasterRepository,
            $this->imageRepository,
            $router,
            new PictureUrlSigner('https://pictures.example.com', 'test-secret', $v2),
            ['en', 'fr'],
        );
    }

    private function createCoaster(int $id, ?string $slug): Coaster
    {
        $coaster = new Coaster();
        $coaster->setName('Coaster '.$id);
        $coaster->setSlug($slug);

        new \ReflectionProperty(Coaster::class, 'id')->setValue($coaster, $id);

        return $coaster;
    }

    private function createImage(int $id, Coaster $coaster): Image
    {
        $image = new Image();
        $image->setCoaster($coaster);
        $image->setFilename($id.'.jpg');
        $image->setWatermarked(true);

        new \ReflectionProperty(Image::class, 'id')->setValue($image, $id);

        return $image;
    }

    // -------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------

    public function testEveryLanguageVersionListsAllVersionsIncludingItself(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 42, 'slug' => 'voltron', 'lastmod' => '2026-10-01 18:30:00'],
        ]);

        $urls = $this->makeService()->getUrlsForPages();

        // Home and ranking, then the coaster, each in both locales.
        self::assertCount(6, $urls);

        $alternates = [
            'en' => 'https://captaincoaster.com/en/show_coaster/42/voltron',
            'fr' => 'https://captaincoaster.com/fr/show_coaster/42/voltron',
        ];
        self::assertSame($alternates['en'], $urls[4]['loc']);
        self::assertSame($alternates, $urls[4]['alternates']);
        self::assertSame($alternates['fr'], $urls[5]['loc']);
        self::assertSame($alternates, $urls[5]['alternates']);
    }

    public function testLastmodIsTheLatestRatingAndNoIgnoredTagIsEmitted(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 1, 'slug' => 'old', 'lastmod' => '2026-09-01 10:00:00'],
            ['id' => 2, 'slug' => 'recent', 'lastmod' => '2026-10-01 18:30:00'],
            ['id' => 3, 'slug' => 'unrated', 'lastmod' => null],
        ]);

        $urls = $this->makeService()->getUrlsForPages();

        // Home carries the latest rating of the whole site.
        self::assertStringStartsWith('2026-10-01T18:30:00', $urls[0]['lastmod'] ?? '');
        self::assertStringStartsWith('2026-09-01T10:00:00', $urls[4]['lastmod'] ?? '');
        self::assertArrayNotHasKey('lastmod', $urls[8]);
        self::assertArrayNotHasKey('changefreq', $urls[0]);
        self::assertArrayNotHasKey('priority', $urls[0]);
    }

    public function testACoasterWithoutSlugIsLeftOut(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 1, 'slug' => null, 'lastmod' => null],
        ]);

        // Home and ranking only.
        self::assertCount(4, $this->makeService()->getUrlsForPages());
    }

    // -------------------------------------------------------------------
    // Images
    // -------------------------------------------------------------------

    public function testPhotosAreGroupedUnderTheirCoasterPage(): void
    {
        $voltron = $this->createCoaster(42, 'voltron-europa-park');
        $taron = $this->createCoaster(43, 'taron-phantasialand');
        $this->imageRepository->method('findForSitemap')->willReturn([
            $this->createImage(100, $voltron),
            $this->createImage(101, $voltron),
            $this->createImage(200, $taron),
        ]);

        $urls = $this->makeService()->getUrlsForImages();

        self::assertCount(2, $urls);
        self::assertSame('https://captaincoaster.com/en/show_coaster/42/voltron-europa-park', $urls[0]['loc']);
        self::assertCount(2, $urls[0]['images']);
        self::assertCount(1, $urls[1]['images']);
    }

    public function testImageLocIsTheSignedV2LightboxUrl(): void
    {
        $this->imageRepository->method('findForSitemap')->willReturn([
            $this->createImage(48213, $this->createCoaster(42, 'voltron-europa-park')),
        ]);

        $urls = $this->makeService()->getUrlsForImages();

        self::assertMatchesRegularExpression(
            '#^https://pictures\.example\.com/i/48213/[0-9a-f]{6}/[0-9a-f]{6}/1440x1440/voltron-europa-park\.jpg$#',
            $urls[0]['images'][0],
        );
    }

    public function testImageLocIsTheSignedLegacyUrlWhileV2IsOff(): void
    {
        $this->imageRepository->method('findForSitemap')->willReturn([
            $this->createImage(48213, $this->createCoaster(42, 'voltron-europa-park')),
        ]);

        $urls = $this->makeService(false)->getUrlsForImages();

        self::assertMatchesRegularExpression(
            '#^https://pictures\.example\.com/1440x1440/jpg/48213\.jpg\?s=[0-9a-f]{32}$#',
            $urls[0]['images'][0],
        );
    }

    public function testAnErrorIsNotTurnedIntoAnEmptySitemap(): void
    {
        $this->imageRepository->method('findForSitemap')->willThrowException(new \RuntimeException('database is down'));

        $this->expectException(\RuntimeException::class);

        $this->makeService()->getUrlsForImages();
    }
}

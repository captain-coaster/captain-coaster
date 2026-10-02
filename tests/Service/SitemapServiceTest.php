<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\CoasterRepository;
use App\Repository\ImageRepository;
use App\Repository\ParkRepository;
use App\Service\PictureUrlSigner;
use App\Service\SitemapService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapServiceTest extends TestCase
{
    private CoasterRepository&MockObject $coasterRepository;
    private ParkRepository&MockObject $parkRepository;
    private ImageRepository&MockObject $imageRepository;

    protected function setUp(): void
    {
        $this->coasterRepository = $this->createMock(CoasterRepository::class);
        $this->parkRepository = $this->createMock(ParkRepository::class);
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
            $this->parkRepository,
            $this->imageRepository,
            $router,
            new PictureUrlSigner('https://pictures.example.com', 'test-secret', $v2),
            ['en', 'fr'],
        );
    }

    /** @return array{id: int, filename: string, focalX: ?float, focalY: ?float, watermarked: bool, coasterId: int, coasterSlug: ?string} */
    private function imageRow(int $id, int $coasterId, ?string $coasterSlug): array
    {
        return ['id' => $id, 'filename' => $id.'.jpg', 'focalX' => null, 'focalY' => null, 'watermarked' => true, 'coasterId' => $coasterId, 'coasterSlug' => $coasterSlug];
    }

    // -------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------

    /** @return list<array{loc: string, alternates: array<string, string>, lastmod?: string}> */
    private function pages(): array
    {
        return iterator_to_array($this->makeService()->getUrlsForPages(), false);
    }

    public function testEveryLanguageVersionListsAllVersionsItselfAndXDefault(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 42, 'slug' => 'voltron', 'parkId' => 7, 'updatedAt' => null, 'lastmod' => '2026-10-01 18:30:00'],
        ]);

        $urls = $this->pages();

        // Home and ranking, then the coaster, each in both locales (the park has no row here).
        self::assertCount(6, $urls);

        $alternates = [
            'en' => 'https://captaincoaster.com/en/show_coaster/42/voltron',
            'fr' => 'https://captaincoaster.com/fr/show_coaster/42/voltron',
            'x-default' => 'https://captaincoaster.com/en/show_coaster/42/voltron',
        ];
        self::assertSame($alternates['en'], $urls[4]['loc']);
        self::assertSame($alternates, $urls[4]['alternates']);
        self::assertSame($alternates['fr'], $urls[5]['loc']);
        self::assertSame($alternates, $urls[5]['alternates']);
    }

    public function testLastmodIsTheLatestRatingAndNoIgnoredTagIsEmitted(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 1, 'slug' => 'old', 'parkId' => 7, 'updatedAt' => null, 'lastmod' => '2026-09-01 10:00:00'],
            ['id' => 2, 'slug' => 'recent', 'parkId' => 7, 'updatedAt' => null, 'lastmod' => '2026-10-01 18:30:00'],
            ['id' => 3, 'slug' => 'unrated', 'parkId' => 7, 'updatedAt' => null, 'lastmod' => null],
        ]);

        $urls = $this->pages();

        // Home carries the latest rating of the whole site.
        self::assertStringStartsWith('2026-10-01T18:30:00', $urls[0]['lastmod'] ?? '');
        self::assertStringStartsWith('2026-09-01T10:00:00', $urls[4]['lastmod'] ?? '');
        self::assertArrayNotHasKey('lastmod', $urls[8]);
        self::assertArrayNotHasKey('changefreq', $urls[0]);
        self::assertArrayNotHasKey('priority', $urls[0]);
    }

    public function testCoasterLastmodIsTheLaterOfItsLastEditAndItsLatestRating(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 1, 'slug' => 'edited-after', 'parkId' => 7, 'updatedAt' => new \DateTimeImmutable('2026-10-02 09:00:00'), 'lastmod' => '2026-09-01 10:00:00'],
            ['id' => 2, 'slug' => 'rated-after', 'parkId' => 7, 'updatedAt' => new \DateTimeImmutable('2026-08-01 09:00:00'), 'lastmod' => '2026-09-01 10:00:00'],
            ['id' => 3, 'slug' => 'never-rated', 'parkId' => 7, 'updatedAt' => new \DateTimeImmutable('2026-07-01 09:00:00'), 'lastmod' => null],
        ]);

        $urls = $this->pages();

        self::assertStringStartsWith('2026-10-02T09:00:00', $urls[4]['lastmod'] ?? '');
        self::assertStringStartsWith('2026-09-01T10:00:00', $urls[6]['lastmod'] ?? '');
        self::assertStringStartsWith('2026-07-01T09:00:00', $urls[8]['lastmod'] ?? '');
    }

    public function testParksWithACoasterAreListedWithTheirLatestRating(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 1, 'slug' => 'old', 'parkId' => 7, 'updatedAt' => null, 'lastmod' => '2026-09-01 10:00:00'],
            ['id' => 2, 'slug' => 'recent', 'parkId' => '7', 'updatedAt' => null, 'lastmod' => '2026-10-01 18:30:00'],
            ['id' => 3, 'slug' => 'unrated', 'parkId' => 8, 'updatedAt' => null, 'lastmod' => null],
        ]);
        $this->parkRepository->method('findForSitemap')->willReturn([
            ['id' => 7, 'slug' => 'europa-park'],
            ['id' => 8, 'slug' => 'new-park'],
            ['id' => 9, 'slug' => 'park-without-coaster'],
        ]);

        $urls = $this->pages();

        // Home, ranking, 2 parks, 3 coasters, in both locales.
        self::assertCount(14, $urls);
        self::assertSame('https://captaincoaster.com/en/park_show/7/europa-park', $urls[4]['loc']);
        self::assertStringStartsWith('2026-10-01T18:30:00', $urls[4]['lastmod'] ?? '');
        self::assertSame('https://captaincoaster.com/en/park_show/8/new-park', $urls[6]['loc']);
        self::assertArrayNotHasKey('lastmod', $urls[6]);
        self::assertStringNotContainsString('park-without-coaster', implode(' ', array_column($urls, 'loc')));
    }

    public function testACoasterWithoutSlugIsLeftOut(): void
    {
        $this->coasterRepository->method('findForSitemap')->willReturn([
            ['id' => 1, 'slug' => null, 'parkId' => 7, 'updatedAt' => null, 'lastmod' => null],
        ]);

        // Home and ranking only.
        self::assertCount(4, $this->pages());
    }

    // -------------------------------------------------------------------
    // Images
    // -------------------------------------------------------------------

    public function testPhotosAreGroupedUnderTheirCoasterPage(): void
    {
        $this->imageRepository->method('findForSitemap')->willReturn([
            $this->imageRow(100, 42, 'voltron-europa-park'),
            $this->imageRow(101, 42, 'voltron-europa-park'),
            $this->imageRow(150, 7, null),
            $this->imageRow(200, 43, 'taron-phantasialand'),
        ]);

        $urls = iterator_to_array($this->makeService()->getUrlsForImages(), false);

        // The coaster without a slug has no page to attach its photo to.
        self::assertCount(2, $urls);
        self::assertSame('https://captaincoaster.com/en/show_coaster/42/voltron-europa-park', $urls[0]['loc']);
        self::assertCount(2, $urls[0]['images']);
        self::assertSame('https://captaincoaster.com/en/show_coaster/43/taron-phantasialand', $urls[1]['loc']);
        self::assertCount(1, $urls[1]['images']);
    }

    public function testImageLocIsTheSignedV2LightboxUrl(): void
    {
        $this->imageRepository->method('findForSitemap')->willReturn([$this->imageRow(48213, 42, 'voltron-europa-park')]);

        $urls = iterator_to_array($this->makeService()->getUrlsForImages(), false);

        self::assertMatchesRegularExpression(
            '#^https://pictures\.example\.com/i/48213/[0-9a-f]{6}/[0-9a-f]{6}/1440x1440/voltron-europa-park\.jpg$#',
            $urls[0]['images'][0],
        );
    }

    public function testImageLocIsTheSignedLegacyUrlWhileV2IsOff(): void
    {
        $this->imageRepository->method('findForSitemap')->willReturn([$this->imageRow(48213, 42, 'voltron-europa-park')]);

        $urls = iterator_to_array($this->makeService(false)->getUrlsForImages(), false);

        self::assertMatchesRegularExpression(
            '#^https://pictures\.example\.com/1440x1440/jpg/48213\.jpg\?s=[0-9a-f]{32}$#',
            $urls[0]['images'][0],
        );
    }

    public function testAnErrorIsNotTurnedIntoAnEmptySitemap(): void
    {
        $this->imageRepository->method('findForSitemap')->willThrowException(new \RuntimeException('database is down'));

        $this->expectException(\RuntimeException::class);

        iterator_to_array($this->makeService()->getUrlsForImages(), false);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SitemapUpdateCommand;
use App\Service\SitemapService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SitemapUpdateCommandTest extends TestCase
{
    private SitemapService&MockObject $sitemapService;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->sitemapService = $this->createMock(SitemapService::class);
        $this->cache = new ArrayAdapter();
    }

    private function cached(string $key): mixed
    {
        return $this->cache->getItem($key)->get();
    }

    public function testBothSitemapsAreReplaced(): void
    {
        $this->cache->get('sitemap_image', static fn (): array => [['loc' => 'old', 'images' => []]]);
        $pages = [['loc' => 'https://captaincoaster.com/en/', 'alternates' => []]];
        $images = [['loc' => 'https://captaincoaster.com/en/coasters/1/a', 'images' => ['https://pictures.example.com/i/1.jpg']]];
        $this->sitemapService->method('getUrlsForPages')->willReturn($pages);
        $this->sitemapService->method('getUrlsForImages')->willReturn($images);

        $tester = new CommandTester(new SitemapUpdateCommand($this->sitemapService, $this->cache));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame($pages, $this->cached('sitemap_urls'));
        self::assertSame($images, $this->cached('sitemap_image'));
        self::assertStringContainsString('1 pages, 1 images', $tester->getDisplay());
    }

    public function testAnEmptyBuildKeepsTheCachedSitemap(): void
    {
        $previous = [['loc' => 'https://captaincoaster.com/en/coasters/1/a', 'images' => ['kept']]];
        $this->cache->get('sitemap_image', static fn (): array => $previous);
        $this->sitemapService->method('getUrlsForImages')->willReturn([]);

        $tester = new CommandTester(new SitemapUpdateCommand($this->sitemapService, $this->cache));
        $tester->execute(['--images' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertSame($previous, $this->cached('sitemap_image'));
    }

    public function testAFailedBuildKeepsTheCachedSitemap(): void
    {
        $previous = [['loc' => 'https://captaincoaster.com/en/', 'alternates' => []]];
        $this->cache->get('sitemap_urls', static fn (): array => $previous);
        $this->sitemapService->method('getUrlsForPages')->willThrowException(new \RuntimeException('database is down'));

        $tester = new CommandTester(new SitemapUpdateCommand($this->sitemapService, $this->cache));

        try {
            $tester->execute(['--pages' => true]);
            self::fail('The error must surface in the cron log.');
        } catch (\RuntimeException) {
            self::assertSame($previous, $this->cached('sitemap_urls'));
        }
    }
}

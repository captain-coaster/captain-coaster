<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SitemapUpdateCommand;
use App\Service\Sitemap\SitemapWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SitemapUpdateCommandTest extends TestCase
{
    private SitemapWriter&MockObject $sitemapWriter;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->sitemapWriter = $this->createMock(SitemapWriter::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new SitemapUpdateCommand($this->sitemapWriter, $this->logger));
    }

    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    public function testBothSitemapsAreWrittenByDefault(): void
    {
        $this->sitemapWriter->expects($this->once())->method('writePages')->willReturn(43860);
        $this->sitemapWriter->expects($this->once())->method('writeImages')->willReturn(['pages' => 3325, 'images' => 18842]);
        $this->logger->expects($this->never())->method('error');

        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('sitemap.xml written: 43860 URLs', $this->display($tester));
        self::assertStringContainsString('sitemap_image.xml written: 3325 pages, 18842 images', $this->display($tester));
    }

    public function testAnOptionWritesOnlyItsFile(): void
    {
        $this->sitemapWriter->expects($this->never())->method('writePages');
        $this->sitemapWriter->expects($this->once())->method('writeImages')->willReturn(['pages' => 1, 'images' => 1]);

        $tester = $this->tester();
        $tester->execute(['--images' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testAFailingSitemapIsLoggedAndDoesNotStopTheOther(): void
    {
        $this->sitemapWriter->method('writePages')->willThrowException(new \RuntimeException('database is down'));
        $this->sitemapWriter->expects($this->once())->method('writeImages')->willReturn(['pages' => 1, 'images' => 1]);
        // The error log is what reaches the alerting channel in production.
        $this->logger->expects($this->once())->method('error')->with(
            $this->anything(),
            $this->callback(static fn (array $context): bool => 'sitemap.xml' === $context['file'] && $context['exception'] instanceof \RuntimeException),
        );

        $tester = $this->tester();
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('sitemap.xml was not updated, the previous file is kept: database is down', $this->display($tester));
        self::assertStringContainsString('sitemap_image.xml written', $this->display($tester));
    }
}

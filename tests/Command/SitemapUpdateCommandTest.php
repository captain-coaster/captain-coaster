<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SitemapUpdateCommand;
use App\Service\SitemapWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SitemapUpdateCommandTest extends TestCase
{
    private SitemapWriter&MockObject $sitemapWriter;

    protected function setUp(): void
    {
        $this->sitemapWriter = $this->createMock(SitemapWriter::class);
    }

    public function testBothSitemapsAreWrittenByDefault(): void
    {
        $this->sitemapWriter->expects($this->once())->method('writePages')->willReturn(30960);
        $this->sitemapWriter->expects($this->once())->method('writeImages')->willReturn(['pages' => 3325, 'images' => 18000]);

        $tester = new CommandTester(new SitemapUpdateCommand($this->sitemapWriter));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('30960 URLs', $tester->getDisplay());
        self::assertStringContainsString('3325 pages, 18000 images', $tester->getDisplay());
    }

    public function testTheImagesOptionLeavesThePagesSitemapAlone(): void
    {
        $this->sitemapWriter->expects($this->never())->method('writePages');
        $this->sitemapWriter->expects($this->once())->method('writeImages')->willReturn(['pages' => 1, 'images' => 1]);

        $tester = new CommandTester(new SitemapUpdateCommand($this->sitemapWriter));
        $tester->execute(['--images' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testAWriterErrorFailsTheCommand(): void
    {
        $this->sitemapWriter->method('writePages')->willThrowException(new \RuntimeException('database is down'));

        $this->expectException(\RuntimeException::class);

        new CommandTester(new SitemapUpdateCommand($this->sitemapWriter))->execute(['--pages' => true]);
    }
}

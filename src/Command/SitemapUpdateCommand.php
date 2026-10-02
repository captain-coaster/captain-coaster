<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Sitemap\SitemapWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Regenerates the static sitemap files (public/sitemap.xml, public/sitemap_image.xml); run
 * daily by cron.
 *
 * The two files are independent: one failing leaves its previous file in place (SitemapWriter)
 * and does not stop the other. Each failure is logged as an error, which is what alerts in
 * production, and fails the command.
 */
#[AsCommand(name: 'sitemap:update', description: 'Write the static sitemap files (pages and images)')]
class SitemapUpdateCommand extends Command
{
    public function __construct(
        private readonly SitemapWriter $sitemapWriter,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('pages', null, InputOption::VALUE_NONE, 'Only write sitemap.xml')
            ->addOption('images', null, InputOption::VALUE_NONE, 'Only write sitemap_image.xml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // No option: both files.
        $all = !$input->getOption('pages') && !$input->getOption('images');
        $writers = [];
        if ($all || $input->getOption('pages')) {
            $writers[SitemapWriter::PAGES_FILE] = fn (): string => \sprintf('%d URLs', $this->sitemapWriter->writePages());
        }
        if ($all || $input->getOption('images')) {
            $writers[SitemapWriter::IMAGES_FILE] = function (): string {
                $counts = $this->sitemapWriter->writeImages();

                return \sprintf('%d pages, %d images', $counts['pages'], $counts['images']);
            };
        }

        $failed = false;
        foreach ($writers as $file => $write) {
            $start = microtime(true);

            try {
                $io->writeln(\sprintf('%s written: %s, %.1f s.', $file, $write(), microtime(true) - $start));
            } catch (\Throwable $e) {
                $failed = true;
                $this->logger->error('Sitemap "{file}" was not updated: {message}', ['file' => $file, 'message' => $e->getMessage(), 'exception' => $e]);
                $io->error(\sprintf('%s was not updated, the previous file is kept: %s', $file, $e->getMessage()));
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}

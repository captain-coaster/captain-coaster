<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Sitemap\SitemapWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Regenerates the static sitemap files (public/sitemap.xml, public/sitemap_image.xml). Run
 * daily by cron; an error leaves the previous files in place and fails the command.
 */
#[AsCommand(
    name: 'sitemap:update',
    description: 'Update sitemaps for pages and images.',
    hidden: false,
)]
class SitemapUpdateCommand extends Command
{
    public function __construct(
        private readonly SitemapWriter $sitemapWriter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pages', null, InputOption::VALUE_NONE)
            ->addOption('images', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');

        $updatePages = $input->getOption('pages');
        $updateImages = $input->getOption('images');

        // If no options are provided, update both
        if (!$updatePages && !$updateImages) {
            $updatePages = true;
            $updateImages = true;
        }

        if ($updatePages) {
            $output->writeln(\sprintf('Pages sitemap updated (%d URLs).', $this->sitemapWriter->writePages()));
        }

        if ($updateImages) {
            $counts = $this->sitemapWriter->writeImages();
            $output->writeln(\sprintf('Images sitemap updated (%d pages, %d images).', $counts['pages'], $counts['images']));
        }

        $output->writeln((string) $stopwatch->stop('command'));

        return Command::SUCCESS;
    }
}

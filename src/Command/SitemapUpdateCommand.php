<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SitemapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'sitemap:update',
    description: 'Update sitemaps for pages and images.',
    hidden: false,
)]
class SitemapUpdateCommand extends Command
{
    public function __construct(
        private readonly SitemapService $sitemapService,
        #[Autowire(service: 'sitemap.cache')]
        private readonly CacheInterface $sitemapCache
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
            $this->sitemapCache->delete('sitemap_urls');
            $this->sitemapCache->get('sitemap_urls', fn () => $this->sitemapService->getUrlsForPages());
            $output->writeln('Pages sitemap updated.');
        }

        if ($updateImages) {
            $this->sitemapCache->delete('sitemap_image');
            $this->sitemapCache->get('sitemap_image', fn () => $this->sitemapService->getUrlsForImages());
            $output->writeln('Images sitemap updated.');
        }

        $output->writeln((string) $stopwatch->stop('command'));

        return Command::SUCCESS;
    }
}

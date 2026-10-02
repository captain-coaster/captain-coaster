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
        #[Autowire(service: 'sitemap.cache_pool')]
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

        // Built before the cached copy is replaced: a failed or empty build keeps the sitemap
        // being served instead of caching an empty one.
        if ($updatePages) {
            $urls = $this->sitemapService->getUrlsForPages();
            if ([] === $urls) {
                $output->writeln('<error>Pages sitemap is empty, the cached one is kept.</error>');

                return Command::FAILURE;
            }
            $this->replace('sitemap_urls', $urls);
            $output->writeln(\sprintf('Pages sitemap updated (%d URLs).', \count($urls)));
        }

        if ($updateImages) {
            $urls = $this->sitemapService->getUrlsForImages();
            if ([] === $urls) {
                $output->writeln('<error>Images sitemap is empty, the cached one is kept.</error>');

                return Command::FAILURE;
            }
            $this->replace('sitemap_image', $urls);
            $output->writeln(\sprintf('Images sitemap updated (%d pages, %d images).', \count($urls), array_sum(array_map(static fn (array $url): int => \count($url['images']), $urls))));
        }

        $output->writeln((string) $stopwatch->stop('command'));

        return Command::SUCCESS;
    }

    /** @param list<array<string, mixed>> $urls */
    private function replace(string $key, array $urls): void
    {
        $this->sitemapCache->delete($key);
        $this->sitemapCache->get($key, static fn (): array => $urls);
    }
}

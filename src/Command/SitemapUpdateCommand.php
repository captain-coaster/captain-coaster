<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SitemapService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'sitemap:update',
    description: 'Update sitemaps for pages and images.',
    hidden: false,
)]
class SitemapUpdateCommand extends Command
{
    public function __construct(
        private readonly SitemapService $sitemapService
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pages', null, InputOption::VALUE_NONE)
            ->addOption('iamges', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');

        $cache = new FilesystemAdapter();

        if ($input->getOption('pages')) {
            $urls = $cache->getItem('sitemap_urls');

            if (!$urls->isHit()) {
                $urls->set($this->sitemapService->getUrlsForPages());
                $urls->expiresAfter(\DateInterval::createFromDateString('12 hours'));
                $cache->save($urls);
            }
        }

        if ($input->getOption('images')) {
            $urls = $cache->getItem('sitemap_image');

            if (!$urls->isHit()) {
                $urls->set($this->sitemapService->getUrlsForImages());
                $urls->expiresAfter(\DateInterval::createFromDateString('48 hours'));
                $cache->save($urls);
            }
        }

        $output->writeln((string)$stopwatch->stop('command'));

        return Command::SUCCESS;
    }
}

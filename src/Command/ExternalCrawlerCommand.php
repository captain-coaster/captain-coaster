<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CoasterRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:external-crawler',
    description: 'Add a short description for your command',
)]
class ExternalCrawlerCommand extends Command
{
    private static array $filters = [
        // 'type' => ['#feature > ul:nth-of-type(1) > li:nth-child(1) > a'],
        'length' => [
            'filter' => 'th:contains("Length") + td > span.float',
            'type' => 'int',
            'convert' => 0.3048,
        ],
        'height' => [
            'filter' => 'th:contains("Height") + td > span.float',
            'type' => 'int',
            'convert' => 0.3048,
        ],
        'speed' => [
            'filter' => 'th:contains("Speed") + td > span.float',
            'type' => 'int',
            'convert' => 1.60934,
        ],
        'inversionsNumber' => [
            'filter' => 'th:contains("Inversions") + td',
            'type' => 'int',
        ],
        'status' => [
            'filter' => '#feature > p > a',
            'type' => 'string',
        ],
        'openingDate' => [
            'filter' => '#feature > p > time',
            'type' => 'datetime',
        ],
        'closingDate' => [
            'filter' => 'time:nth-of-type(2)',
            'type' => 'datetime',
        ],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CoasterRepository $coasterRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'Argument description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $externalId = $input->getArgument('id');

        foreach ($this->coasterRepository->findAll() as $coaster) {
            if (null === $coaster->getExternalId()) {
                continue;
            }

            $html = $this
                ->httpClient
                ->request('GET', \sprintf('https://url', $coaster->getExternalId()))
                ->getContent();

            dump($coaster->getName());

            // Fix length
            $newLength = $this->getLength($html);
            dump('Old length: '.$coaster->getLength(), 'New length: '.$newLength);

            // Fix opening date
            dump('Old opening date: '.$coaster->getOpeningDate()->format('Y-m-d'), 'New opening date: '.$this->getOpeningDate($html));

            continue;
            foreach (self::$filters as $type => $filter) {
                $externalValue = $this->extractValue($html, $filter);

                if (null === $externalValue) {
                    continue;
                }

                $getter = 'get'.ucfirst($type);

                if ($coaster->$getter() instanceof \DateTimeInterface) {
                    $internalValue = $coaster->$getter()->format('Y-m-d');
                } else {
                    $internalValue = $coaster->$getter();
                }

                dump($coaster->getName(), 'Old value: '.$internalValue, 'New value: '.$externalValue);
            }

            $this->getOpeningDate($html);
            $this->getClosingDate($html);
        }

        $io->success('Done.');

        return Command::SUCCESS;
    }

    private function extractValue($html, $filter): int|string|null
    {
        $crawler = new Crawler($html);

        try {
            $value = $crawler->filter($filter['filter'])->text();
        } catch (\InvalidArgumentException) {
            return null;
        }

        if (isset($filter['convert'])) {
            $value *= $filter['convert'];
        }

        switch ($filter['type']) {
            case 'int':
                $value = (int) $value;
                break;
            case 'string':
                $value = (string) $value;
                break;
            case 'datetime':
                dump($value);
                $value = (new \DateTime($value))->format('Y-m-d');
        }

        return $value;
    }

    private function getLength($html): ?int
    {
        $crawler = new Crawler($html);

        try {
            $length = $crawler->filter('th:contains("Length") + td > span.float')->text();
        } catch (\InvalidArgumentException) {
            return null;
        }

        return (int) (round(($length * 0.3048) / 5) * 5);
    }

    private function getOpeningDate($html): ?string
    {
        $crawler = new Crawler($html);
        try {
            $value = $crawler->filter('#feature > p > time')->eq(0)->attr('datetime');
        } catch (\InvalidArgumentException) {
            return null;
        }

        $value = (new \DateTime($value))->format('Y-m-d');

        return $value;
    }

    private function getClosingDate($html): ?string
    {
        $crawler = new Crawler($html);
        try {
            $value = $crawler->filter('#feature > p > time')->eq(1)->attr('datetime');
        } catch (\InvalidArgumentException) {
            return null;
        }

        dump($value);
        if (4 === \strlen($value)) {
            $value .= '-01-01';
        }
        $value = (new \DateTime($value))->format('Y-m-d');
        dump($value);

        return $value;
    }
}

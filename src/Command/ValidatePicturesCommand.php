<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'app:validate-pictures',
    description: 'Auto enable pictures, not enabled for more than 23 hours',
)]
class ValidatePicturesCommand extends Command
{
    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(string:PICTURES_CDN)%')]
        private string $imagesEndpoint
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stopwatch = new Stopwatch();
        $stopwatch->start('pictures');
        $io->note('Start validating pictures.');

        $pictures = $this->imageRepository->findImageToBeValidated();

        foreach ($pictures as $picture) {
            $io->note('Enabling: '.$this->imagesEndpoint.'/1440x1440/'.$picture->getFilename());
            $picture->setEnabled(true);
            $this->em->persist($picture);
        }

        $this->em->flush();

        $io->success((string) $stopwatch->stop('pictures'));

        return Command::SUCCESS;
    }
}

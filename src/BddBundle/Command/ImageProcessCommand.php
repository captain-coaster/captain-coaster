<?php

namespace BddBundle\Command;

use BddBundle\Entity\Image;
use BddBundle\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ImageProcessCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ImageManager
     */
    private $imageManager;

    /**
     * ImageGenerateCacheCommand constructor.
     * @param EntityManagerInterface $em
     * @param ImageManager $imageManager
     */
    public function __construct(EntityManagerInterface $em, ImageManager $imageManager)
    {
        parent::__construct();
        $this->em = $em;
        $this->imageManager = $imageManager;
    }

    protected function configure()
    {
        $this
            ->setName('image:process')
            ->setDescription('Process an image after upload.')
            ->addArgument('image-id', InputArgument::OPTIONAL, 'ID of image');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('process-image');
        $output->writeln('Start processing image');

//        $argument = $input->getArgument('argument');

        $image = $this->em->getRepository('BddBundle:Image')->findOneBy(
            ['enabled' => false, 'optimized' => false],
            ['updatedAt' => 'asc']
        );

        if (!$image instanceof Image) {
            $output->writeln('No image to process.');

            return;
        }

        // Resize image
        $output->writeln('Resizing '.$image->getFilename());
        if (!$this->imageManager->resize($image)) {
            $output->writeln('Not resized.');
        }

        // Add watermark if needed
        $output->writeln('Watermarking '.$image->getFilename());
        if (!$this->imageManager->watermark($image)) {
            $output->writeln('Not watermarked.');
        }

        // Optimize image
        $output->writeln('Optimizing '.$image->getFilename());
        if (!$this->imageManager->optimize($image)) {
            $output->writeln('Not optimized.');
        }

//        $this->generateCache($image);

        $this->imageManager->enableImage($image);
        $output->writeln('Image processed and enabled !');

        $event = $stopwatch->stop('process-image');
        $output->writeln(($event->getDuration() / 1000).' s');
        $output->writeln(($event->getMemory() / 1000 / 1000).' mo');
    }
}

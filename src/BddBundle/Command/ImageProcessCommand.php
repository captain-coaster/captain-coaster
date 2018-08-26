<?php

namespace BddBundle\Command;

use BddBundle\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
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
     *
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
            ->setDescription('Process an image after upload (autorotate, resize, watermark, optimize).');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('process-image');
        $output->writeln('Start processing image');

        $images = $this->em->getRepository('BddBundle:Image')->findBy(
            ['optimized' => false],
            ['updatedAt' => 'asc'],
            10
        );

        $command = $this->getApplication()->find('liip:imagine:cache:resolve');

        foreach ($images as $image) {
            $output->writeln('Processing '.$image->getFilename());

            if (!$this->imageManager->process($image)) {
                $output->writeln('Problem during image process.');
                continue;
            }

            $path = $image->getPath();
            $arguments = [
                'command' => 'liip:imagine:cache:resolve',
                'paths' => [$path],
                '--force' => true,
            ];

            $output->writeln('Generating cache for '.$image->getFilename());
            try {
                $command->run(new ArrayInput($arguments), $output);
            } catch (\Exception $e) {
                $output->writeln('Unable to generate cache');
            }

            $output->writeln('Image processed');

            sleep(2);
        }

        $event = $stopwatch->stop('process-image');
        $output->writeln(($event->getDuration() / 1000).' s');
        $output->writeln(($event->getMemory() / 1000 / 1000).' mo');
    }
}

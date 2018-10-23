<?php

namespace BddBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Stopwatch\Stopwatch;

class ImageConsistencyCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * ImageGenerateCacheCommand constructor.
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setName('image:consistency')
            ->setDescription('Check consisteny between database and filesystem');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('cache-image');
        $output->writeln('Start generating cache');

        $images = $this->em->getRepository('BddBundle:Image')->findBy([], ['updatedAt' => 'desc']);

        $fs = new Filesystem();
        $basePath = $this->getContainer()->getParameter('base_path_images');
        $filenames = [];

        // search for missing image files
        foreach ($images as $image) {
            $filenames[] = $image->getFilename();

            if (!$fs->exists(sprintf('%s/%s', $basePath, $image->getPath()))) {
                $output->writeln('Missing file '. $image->getPath());
            }
        }

        // search for orphan files
        $finder = new Finder();
        $finder->files()->in(sprintf('%s/%s', $basePath, '*/*'));
        foreach ($finder as $file) {
            if (!in_array($file->getFilename(), $filenames)) {
                $output->writeln('Orphan file '.$file->getFilename());
            }
        }
    }
}

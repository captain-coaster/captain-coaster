<?php

namespace BddBundle\Command;

use BddBundle\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteImagesCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var ImageManager
     */
    private $manager;

    /**
     * ImageGenerateCacheCommand constructor.
     * @param EntityManagerInterface $em
     * @param ImageManager $manager
     */
    public function __construct(EntityManagerInterface $em, ImageManager $manager)
    {
        parent::__construct();
        $this->em = $em;
        $this->manager = $manager;
    }

    protected function configure()
    {
        $this
            ->setName('DeleteImages')
            ->setDescription('...');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $imagesToDelete = $this->em->getRepository('BddBundle:Image')->findBy(['enabled' => false]);

        $output->writeln('Deleting '.count($imagesToDelete).' images');

        sleep(10);

        foreach ($imagesToDelete as $image) {
            try {
                $this->manager->remove($image->getFilename());
                $output->writeln('Removing '.$image->getFilename());
            } catch (\Exception $e) {
                $output->writeln('Error '.$e->getMessage());
            }
        }
    }
}

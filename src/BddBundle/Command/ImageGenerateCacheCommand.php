<?php

namespace BddBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ImageGenerateCacheCommand extends ContainerAwareCommand
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

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('image:generate-cache')
            ->setDescription('Generate caches for images');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('cache-image');
        $output->writeln('Start generating cache');

        $images = $this->em->getRepository('BddBundle:Image')->findBy([], ['updatedAt' => 'desc']);
        $command = $this->getApplication()->find('liip:imagine:cache:resolve');

        foreach ($images as $image) {
            $path = $image->getPath();
            $arguments = array(
                'command' => 'liip:imagine:cache:resolve',
                'paths' => [$path],
            );

            $greetInput = new ArrayInput($arguments);
            $command->run($greetInput, $output);
            sleep(2);
        }

        $output->writeln('End of command.');
        $event = $stopwatch->stop('cache-image');
        $output->writeln(($event->getDuration() / 1000).' s');
        $output->writeln(($event->getMemory() / 1000 / 1000).' mo');
    }

}

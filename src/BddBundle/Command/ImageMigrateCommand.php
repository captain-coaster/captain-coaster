<?php

namespace BddBundle\Command;

use BddBundle\Entity\Image;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Ramsey\Uuid\Uuid;

class ImageMigrateCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('image:migrate');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $basePath = $this->getContainer()->getParameter('base_path_images');
        $targetPath = '/tmp';

        $coasters = $em->getRepository('BddBundle:Coaster')->findAll();

        foreach ($coasters as $coaster) {
            $id = $coaster->getId();

            $path = sprintf('%s/%d/big/', $basePath, $id);
            $fs = new Filesystem();

            if (!$fs->exists($path)) {
                $output->writeln('Ignore '.$id);
                continue;
            }

            // Find image files on disk
            $finder = new Finder();
            $finder
                ->files()
                ->in($path)
                ->name('*.jpg');

            // Generate URLs
            foreach ($finder as $file) {
                $filename = $file->getFilename();
                $extension = $file->getExtension();

                $newBasename = Uuid::uuid4()->toString();
                $targetFolder = substr($newBasename, 0, 1);

                $image = new Image();
                $image->setFilename($newBasename.$extension);
                $image->setCoaster($coaster);

                $fs2 = new Filesystem();

                if (!$fs2->exists($targetPath.'/'.$targetFolder)) {
                    $fs2->mkdir($targetPath.'/'.$targetFolder);
                }

                try {
                    $fs2->copy(
                        sprintf('%s/%d/big/%s', $basePath, $id, $filename),
                        sprintf('%s/%s/%s.%s', $targetPath, $targetFolder, $newBasename, $extension)
                    );
                } catch (\Exception $e) {
                    $output->writeln('Error '.sprintf('%s/%d/big/%s', $basePath, $id, $filename));
                    continue;
                }

                $em->persist($image);
                $em->flush();
            }
        }

        $output->writeln('Command result.');
    }

}

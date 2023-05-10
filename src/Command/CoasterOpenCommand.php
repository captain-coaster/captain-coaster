<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Coaster;
use App\Entity\Status;
use App\Repository\CoasterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class CoasterOpenCommand extends Command
{
    protected static $defaultName = 'coaster:open';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CoasterRepository      $coasterRepository,
        private readonly ChatterInterface       $chatter,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Checks if a coaster opens today and change its status.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $today = new \DateTime();

        if ($today->format('dm') === '0101') {
            $output->writeln('No opening first day of year.');

            return;
        }

        $openingCoasters = $this->coasterRepository->findBy(
            [
                'openingDate' => $today,
            ]
        );

        $operatingStatus = $this->coasterRepository->findOneBy(
            ['name' => Status::OPERATING]
        );

        /** @var Coaster $coaster */
        foreach ($openingCoasters as $coaster) {
            $coaster->setStatus($operatingStatus);
            $this->em->persist($coaster);
            $this->em->flush();

            $this->chatter->send(
                (new ChatMessage('We just opened ' . $coaster->getName() . ' at ' . $coaster->getPark()->getName() . '! 🎉'))->transport('discord_notif')
            );
        }
    }
}

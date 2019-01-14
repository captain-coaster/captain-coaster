<?php

namespace App\Command;

use App\Entity\User;
use App\Service\BadgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class BadgeGiveCommand extends Command
{
    /**
     * @var BadgeService
     */
    private $badgeService;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * BadgeGiveCommand constructor.
     * @param BadgeService $badgeService
     * @param EntityManagerInterface $em
     */
    public function __construct(BadgeService $badgeService, EntityManagerInterface $em)
    {
        parent::__construct();
        $this->badgeService = $badgeService;
        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setName('badge:give')
            ->setDescription('Give badges to users')
            ->addArgument('user', InputArgument::OPTIONAL, 'User ID');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('badge');
        $output->writeln('Start giving badges.');

        $userId = $input->getArgument('user');

        if (!is_null($userId)) {
            $users[] = $this->em->getRepository(User::class)->findOneBy(['id' => $userId]);
        } else {
            $users = $this->em->getRepository(User::class)->getUsersWithRecentRatingOrTopUpdate();
        }

        /** @var User $user */
        foreach ($users as $user) {
            $output->writeln("Checking $user...");
            $this->badgeService->give($user);
        }

        $output->writeln('End of command.');
        $output->writeln((string)$stopwatch->stop('badge'));
    }
}

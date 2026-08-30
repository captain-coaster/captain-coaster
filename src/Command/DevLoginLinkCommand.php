<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Logs into a local session as any user, for reproducing logged-in-only behaviour
 * against a production dump.
 *
 * Deliberately a console command reusing the existing login_link firewall rather
 * than a /dev/login route: it adds no routable surface, and it authenticates
 * through the same signed, expiring, use-capped path a real magic link takes.
 */
#[AsCommand(
    name: 'app:dev:login-link',
    description: 'Print a one-shot login link for a user (dev environment only)'
)]
class DevLoginLinkCommand extends Command
{
    public function __construct(
        // The firewall-aware handler cannot resolve a firewall without a request.
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly UserRepository $userRepository,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::OPTIONAL, 'User id or email', '1')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Host the link should point at', 'https://127.0.0.1:8000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('dev' !== $this->environment) {
            $io->error(\sprintf('Refusing to run in the "%s" environment.', $this->environment));

            return Command::FAILURE;
        }

        $identifier = (string) $input->getArgument('user');
        $user = ctype_digit($identifier)
            ? $this->userRepository->find((int) $identifier)
            : $this->userRepository->findOneBy(['email' => $identifier]);

        if (null === $user) {
            $io->error(\sprintf('No user matching "%s".', $identifier));

            return Command::FAILURE;
        }

        $link = $this->loginLinkHandler->createLoginLink($user, Request::create((string) $input->getOption('base-url')));

        $io->success(\sprintf('%s (id %d) — link is single-session, expires per login_link config', $user->getEmail(), $user->getId()));
        $io->writeln($link->getUrl());

        return Command::SUCCESS;
    }
}

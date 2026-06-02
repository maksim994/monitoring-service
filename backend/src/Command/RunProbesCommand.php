<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CheckRepository;
use App\Service\Probe\ProbeRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:run-probes',
    description: 'Run external HTTP and SSL probes for enabled checks',
)]
final class RunProbesCommand extends Command
{
    public function __construct(
        private readonly CheckRepository $checkRepository,
        private readonly ProbeRunner $probeRunner,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(default:local-1:PROBE_ID)%')]
        private readonly string $probeId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('probe-id', null, InputOption::VALUE_REQUIRED, 'Probe node identifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $probeId = (string) ($input->getOption('probe-id') ?: $this->probeId);

        foreach ($this->checkRepository->findEnabledForProbes() as $check) {
            $result = $this->probeRunner->runCheck($check, $probeId);
            $this->entityManager->persist($result);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}

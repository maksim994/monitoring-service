<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CheckRepository;
use App\Repository\SiteRepository;
use App\Service\Check\CheckProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:provision-checks',
    description: 'Create default checks for sites that do not have them yet',
)]
final class ProvisionChecksCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly CheckRepository $checkRepository,
        private readonly CheckProvisioner $checkProvisioner,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $totalCreated = 0;

        foreach ($this->siteRepository->findAll() as $site) {
            $existing = $this->checkRepository->findBySite($site);
            if ($existing === []) {
                $this->checkProvisioner->provisionForSite($site);
                $totalCreated += 7;
                $output->writeln(sprintf('Provisioned all checks for site %s', $site->getDomain()));

                continue;
            }

            $created = $this->checkProvisioner->provisionMissingForSite($site);
            if ($created > 0) {
                $output->writeln(sprintf('Added %d check(s) for site %s', $created, $site->getDomain()));
                $totalCreated += $created;
            }
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('Done. Created %d check record(s).', $totalCreated));

        return Command::SUCCESS;
    }
}

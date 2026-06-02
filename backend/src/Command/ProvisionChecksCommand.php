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
        foreach ($this->siteRepository->findAll() as $site) {
            if ($this->checkRepository->findBySite($site) !== []) {
                continue;
            }

            $this->checkProvisioner->provisionForSite($site);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}

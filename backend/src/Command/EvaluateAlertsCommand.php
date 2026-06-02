<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SiteRepository;
use App\Service\Alert\AlertEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:evaluate-alerts',
    description: 'Evaluate alert rules for all active sites',
)]
final class EvaluateAlertsCommand extends Command
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly AlertEngine $alertEngine,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->siteRepository->findAllActiveForAlerts() as $site) {
            $this->alertEngine->evaluateSite($site);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}

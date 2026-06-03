<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Site;
use App\Message\MetricsBatchMessage;
use App\Repository\SiteRepository;
use App\Service\Alert\AlertEngine;
use App\Service\Check\CheckSnapshotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class MetricsBatchMessageHandler
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly AlertEngine $alertEngine,
        private readonly CheckSnapshotService $checkSnapshotService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(MetricsBatchMessage $message): void
    {
        $site = $this->siteRepository->find(Uuid::fromString($message->siteId));
        if ($site === null) {
            return;
        }

        $metrics = $message->payload['metrics'] ?? [];
        if (!is_array($metrics)) {
            return;
        }

        /** @var array<string, array<string, mixed>> $agentsMetrics */
        $agentsMetrics = [];

        foreach ($metrics as $metric) {
            if (!is_array($metric)) {
                continue;
            }

            $key = $metric['key'] ?? null;
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'agents.')) {
                $agentsMetrics[$key] = $metric;

                continue;
            }

            match ($key) {
                'disk.free_percent' => $this->handleDiskMetric($site, $metric),
                'backup.age_hours' => $this->handleBackupMetric($site, $metric),
                'modules.updates_available_count' => $this->handleModulesMetric($site, $metric),
                'license.days_left' => $this->handleLicenseMetric($site, $metric),
                default => null,
            };
        }

        if ($agentsMetrics !== []) {
            $this->alertEngine->onAgentsMetrics($site, $agentsMetrics);
            $this->checkSnapshotService->recordAgents($site, $agentsMetrics);
        }

        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $metric */
    private function handleDiskMetric(Site $site, array $metric): void
    {
        $value = $metric['value'] ?? null;
        if (!is_numeric($value)) {
            return;
        }

        $this->alertEngine->onDiskMetric($site, (float) $value, $metric);
        $this->checkSnapshotService->recordDisk($site, $metric);
    }

    /** @param array<string, mixed> $metric */
    private function handleBackupMetric(Site $site, array $metric): void
    {
        $this->alertEngine->onBackupMetric($site, $metric);
        $this->checkSnapshotService->recordBackup($site, $metric);
    }

    /** @param array<string, mixed> $metric */
    private function handleModulesMetric(Site $site, array $metric): void
    {
        $this->alertEngine->onModulesUpdatesMetric($site, $metric);
        $this->checkSnapshotService->recordModules($site, $metric);
    }

    /** @param array<string, mixed> $metric */
    private function handleLicenseMetric(Site $site, array $metric): void
    {
        $this->alertEngine->onBitrixLicenseMetric($site, $metric);
        $this->checkSnapshotService->recordLicense($site, $metric);
    }
}

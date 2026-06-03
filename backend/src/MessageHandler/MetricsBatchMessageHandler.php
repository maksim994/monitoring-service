<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Site;
use App\Message\MetricsBatchMessage;
use App\Repository\SiteRepository;
use App\Service\Alert\AlertEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class MetricsBatchMessageHandler
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly AlertEngine $alertEngine,
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
                'backup.age_hours' => $this->alertEngine->onBackupMetric($site, $metric),
                'modules.updates_available_count' => $this->alertEngine->onModulesUpdatesMetric($site, $metric),
                default => null,
            };
        }

        if ($agentsMetrics !== []) {
            $this->alertEngine->onAgentsMetrics($site, $agentsMetrics);
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
    }
}

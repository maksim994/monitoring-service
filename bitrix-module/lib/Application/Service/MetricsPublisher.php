<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Service;

use Vendor\Monitoring\Application\Collector\AgentsCollector;
use Vendor\Monitoring\Application\Collector\BackupCollector;
use Vendor\Monitoring\Application\Collector\DiskCollector;
use Vendor\Monitoring\Application\Collector\EnvironmentCollector;
use Vendor\Monitoring\Application\Collector\LicenseCollector;
use Vendor\Monitoring\Application\Collector\ModulesCollector;

final class MetricsPublisher
{
    /** @return list<array<string, mixed>> */
    public function collectMetrics(): array
    {
        return array_merge(
            (new DiskCollector())->collect(),
            (new BackupCollector())->collect(),
            (new AgentsCollector())->collect(),
            (new ModulesCollector())->collect(),
            (new LicenseCollector())->collect(),
        );
    }

    /**
     * Heartbeat + все метрики collectors (как CollectorAgent).
     *
     * @return array{heartbeat: array<string, mixed>, metrics: array<string, mixed>, metricsCount: int}
     */
    public function publishAll(): array
    {
        $sender = new ModuleSender();
        $environment = (new EnvironmentCollector())->collect();
        $heartbeat = $sender->sendHeartbeat($environment);

        $metrics = $this->collectMetrics();
        $metricsResult = [
            'success' => true,
            'status' => 204,
            'response' => '',
        ];

        if ($metrics !== []) {
            $metricsResult = $sender->sendMetricsBatch($metrics);
        }

        $sender->flushQueue();

        return [
            'heartbeat' => $heartbeat,
            'metrics' => $metricsResult,
            'metricsCount' => count($metrics),
        ];
    }
}

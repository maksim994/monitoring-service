<?php

declare(strict_types=1);

namespace App\Service\Check;

use App\Entity\Check;
use App\Entity\CheckResult;
use App\Entity\Site;
use App\Repository\CheckRepository;
use App\Repository\CheckResultRepository;

final class CheckSnapshotService
{
    public function __construct(
        private readonly CheckRepository $checkRepository,
        private readonly CheckResultRepository $checkResultRepository,
        private readonly CheckThresholdResolver $checkThresholdResolver,
    ) {
    }

    /** @return array{status: string, value: array<string, mixed>, collectedAt: string}|null */
    public function resolveForApi(Check $check): ?array
    {
        if ($check->getLastCollectedAt() !== null && $check->getLastStatus() !== null) {
            return [
                'status' => $check->getLastStatus(),
                'value' => $check->getLastValueJson() ?? [],
                'collectedAt' => $check->getLastCollectedAt()->format(DATE_ATOM),
            ];
        }

        $latest = $this->checkResultRepository->findLatestForCheck($check);
        if ($latest === null) {
            return null;
        }

        return [
            'status' => $latest->getStatus(),
            'value' => $latest->getValueJson(),
            'collectedAt' => $latest->getCheckedAt()->format(DATE_ATOM),
        ];
    }

    /** @param array<string, mixed> $value */
    public function record(Check $check, string $status, array $value, ?\DateTimeImmutable $collectedAt = null): void
    {
        $check->recordSnapshot($status, $value, $collectedAt);
    }

    public function recordFromProbeResult(CheckResult $result): void
    {
        $this->record($result->getCheck(), $result->getStatus(), $result->getValueJson(), $result->getCheckedAt());
    }

    public function recordHeartbeat(Site $site): void
    {
        $check = $this->checkRepository->findBySiteAndType($site, Check::TYPE_HEARTBEAT_MISSING);
        if ($check === null) {
            return;
        }

        $lastAt = $site->getLastHeartbeatAt();
        if ($lastAt === null) {
            $this->record($check, CheckResult::STATUS_UNKNOWN, ['lastHeartbeatAt' => null]);

            return;
        }

        $seconds = max(0, time() - $lastAt->getTimestamp());
        $status = CheckResult::STATUS_OK;

        $this->record($check, $status, [
            'lastHeartbeatAt' => $lastAt->format(DATE_ATOM),
            'secondsSinceLastHeartbeat' => $seconds,
        ], $lastAt);
    }

    /** @param array<string, mixed> $metric */
    public function recordDisk(Site $site, array $metric): void
    {
        $check = $this->checkRepository->findBySiteAndType($site, Check::TYPE_DISK_LOW);
        if ($check === null) {
            return;
        }

        $freePercent = is_numeric($metric['value'] ?? null) ? (float) $metric['value'] : null;
        if ($freePercent === null) {
            return;
        }

        $tags = is_array($metric['tags'] ?? null) ? $metric['tags'] : [];
        $thresholds = $this->checkThresholdResolver->disk($site);
        $status = $this->diskStatus($freePercent, $thresholds['warningPercent'], $thresholds['criticalPercent']);

        $this->record($check, $status, [
            'freePercent' => $freePercent,
            'freeBytes' => $tags['freeBytes'] ?? null,
            'totalBytes' => $tags['totalBytes'] ?? null,
            'usedBytes' => $tags['usedBytes'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $metric */
    public function recordBackup(Site $site, array $metric): void
    {
        $check = $this->checkRepository->findBySiteAndType($site, Check::TYPE_BACKUP_STALE);
        if ($check === null) {
            return;
        }

        $tags = is_array($metric['tags'] ?? null) ? $metric['tags'] : [];
        $backupStatus = is_string($tags['status'] ?? null) ? $tags['status'] : 'unknown';
        $ageHours = is_numeric($metric['value'] ?? null) ? (float) $metric['value'] : null;
        $thresholds = $this->checkThresholdResolver->backup($site);

        $status = CheckResult::STATUS_OK;
        if ($backupStatus === 'missing' || $ageHours === null) {
            $status = CheckResult::STATUS_WARNING;
        } elseif ($ageHours >= $thresholds['criticalHours']) {
            $status = CheckResult::STATUS_CRITICAL;
        } elseif ($ageHours >= $thresholds['warningHours']) {
            $status = CheckResult::STATUS_WARNING;
        }

        $this->record($check, $status, [
            'ageHours' => $ageHours,
            'backupStatus' => $backupStatus,
            'lastBackupAt' => $tags['lastBackupAt'] ?? null,
            'lastBackupName' => $tags['lastBackupName'] ?? null,
        ]);
    }

    /** @param array<string, array<string, mixed>> $metrics */
    public function recordAgents(Site $site, array $metrics): void
    {
        $check = $this->checkRepository->findBySiteAndType($site, Check::TYPE_AGENTS_LAG);
        if ($check === null) {
            return;
        }

        $maxLagSeconds = $this->metricInt($metrics['agents.max_lag_seconds'] ?? null);
        $overdueCount = $this->metricInt($metrics['agents.overdue_count'] ?? null);
        $activeCount = $this->metricInt($metrics['agents.active_count'] ?? null);
        $thresholds = $this->checkThresholdResolver->agents($site);

        $status = CheckResult::STATUS_OK;
        if ($maxLagSeconds >= $thresholds['criticalLagSeconds']) {
            $status = CheckResult::STATUS_CRITICAL;
        } elseif ($maxLagSeconds >= $thresholds['warningLagSeconds']) {
            $status = CheckResult::STATUS_WARNING;
        }

        $overdueMetric = $metrics['agents.overdue_count'] ?? null;
        $tags = is_array($overdueMetric) && is_array($overdueMetric['tags'] ?? null) ? $overdueMetric['tags'] : [];

        $this->record($check, $status, [
            'maxLagSeconds' => $maxLagSeconds,
            'overdueCount' => $overdueCount,
            'activeCount' => $activeCount,
            'stuckAgents' => $tags['stuckAgents'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $metric */
    public function recordModules(Site $site, array $metric): void
    {
        $check = $this->checkRepository->findBySiteAndType($site, Check::TYPE_MODULES_UPDATES);
        if ($check === null) {
            return;
        }

        $tags = is_array($metric['tags'] ?? null) ? $metric['tags'] : [];
        $statusTag = is_string($tags['status'] ?? null) ? $tags['status'] : 'unknown';
        $updatesCount = is_numeric($metric['value'] ?? null) ? (int) $metric['value'] : null;

        if ($statusTag === 'unknown' || $updatesCount === null) {
            $this->record($check, CheckResult::STATUS_UNKNOWN, ['updatesAvailableCount' => null]);

            return;
        }

        $warningCount = $this->checkThresholdResolver->modulesWarningCount($site);
        $status = $updatesCount >= $warningCount ? CheckResult::STATUS_WARNING : CheckResult::STATUS_OK;

        $this->record($check, $status, [
            'updatesAvailableCount' => $updatesCount,
        ]);
    }

    /** @param array<string, mixed> $metric */
    public function recordLicense(Site $site, array $metric): void
    {
        $check = $this->checkRepository->findBySiteAndType($site, Check::TYPE_BITRIX_LICENSE_EXPIRY);
        if ($check === null) {
            return;
        }

        $tags = is_array($metric['tags'] ?? null) ? $metric['tags'] : [];
        $statusTag = is_string($tags['status'] ?? null) ? $tags['status'] : 'unknown';

        if ($statusTag === 'unknown') {
            $this->record($check, CheckResult::STATUS_UNKNOWN, []);

            return;
        }

        if ($statusTag === 'unlimited') {
            $this->record($check, CheckResult::STATUS_OK, [
                'unlimited' => true,
                'edition' => $tags['edition'] ?? null,
            ]);

            return;
        }

        $daysLeft = is_numeric($metric['value'] ?? null) ? (int) $metric['value'] : null;
        if ($daysLeft === null) {
            $this->record($check, CheckResult::STATUS_UNKNOWN, []);

            return;
        }

        $thresholds = $this->checkThresholdResolver->bitrixLicense($site);
        $status = CheckResult::STATUS_OK;
        if ($daysLeft < $thresholds['criticalDays']) {
            $status = CheckResult::STATUS_CRITICAL;
        } elseif ($daysLeft < $thresholds['warningDays']) {
            $status = CheckResult::STATUS_WARNING;
        }

        $this->record($check, $status, [
            'daysLeft' => $daysLeft,
            'source' => $tags['source'] ?? null,
            'edition' => $tags['edition'] ?? null,
            'productExpireDate' => $tags['productExpireDate'] ?? null,
            'supportExpireDate' => $tags['supportExpireDate'] ?? null,
            'productDaysLeft' => $tags['productDaysLeft'] ?? null,
            'supportDaysLeft' => $tags['supportDaysLeft'] ?? null,
        ]);
    }

    private function diskStatus(float $freePercent, float $warningPercent, float $criticalPercent): string
    {
        if ($freePercent < $criticalPercent) {
            return CheckResult::STATUS_CRITICAL;
        }

        if ($freePercent < $warningPercent) {
            return CheckResult::STATUS_WARNING;
        }

        return CheckResult::STATUS_OK;
    }

    /** @param array<string, mixed>|null $metric */
    private function metricInt(?array $metric): int
    {
        if ($metric === null) {
            return 0;
        }

        $value = $metric['value'] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}

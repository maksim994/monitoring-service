<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Service\Notification\IncidentNotificationFormatter;
use App\Entity\Check;
use App\Entity\CheckResult;
use App\Entity\Incident;
use App\Entity\IncidentEvent;
use App\Entity\Site;
use App\Repository\IncidentRepository;
use App\Service\Check\CheckMonitoringGate;
use App\Service\Check\CheckThresholdResolver;
use App\Service\Notification\NotificationDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AlertEngine
{
    private const FINGERPRINT_DEFAULT = 'default';

    public function __construct(
        private readonly IncidentRepository $incidentRepository,
        private readonly SiteStatusResolver $siteStatusResolver,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly MaintenanceWindowService $maintenanceWindowService,
        private readonly CheckThresholdResolver $checkThresholdResolver,
        private readonly CheckMonitoringGate $checkMonitoringGate,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(int:HEARTBEAT_WARNING_SECONDS)%')]
        private readonly int $heartbeatWarningSeconds,
        #[Autowire('%env(int:HEARTBEAT_CRITICAL_SECONDS)%')]
        private readonly int $heartbeatCriticalSeconds,
    ) {
    }

    public function onHeartbeatReceived(Site $site): void
    {
        $this->resolveCheckType($site, Incident::CHECK_HEARTBEAT_MISSING);
        $this->siteStatusResolver->sync($site);
    }

    public function resolveIncidentsForCheckType(Site $site, string $checkType): void
    {
        $this->resolveCheckType($site, $checkType);
        $this->siteStatusResolver->sync($site);
    }

    public function onProbeResult(Check $check, CheckResult $result): void
    {
        $site = $check->getSite();
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        if (!$check->isEnabled()) {
            $this->resolveCheckType($site, $check->getType());
            $this->siteStatusResolver->sync($site);

            return;
        }

        match ($check->getType()) {
            Check::TYPE_UPTIME_HTTP => $this->evaluateUptimeResult($check, $result),
            Check::TYPE_SSL_EXPIRY => $this->evaluateSslResult($check, $result),
            Check::TYPE_DOMAIN_EXPIRY => $this->evaluateDomainResult($check, $result),
            default => null,
        };

        $this->siteStatusResolver->sync($site);
    }

    /** @param array<string, mixed> $metricValue */
    public function onDiskMetric(Site $site, float $freePercent, array $metricValue): void
    {
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        if ($this->skipDisabledCheck($site, Incident::CHECK_DISK_LOW)) {
            return;
        }

        $diskThresholds = $this->checkThresholdResolver->disk($site);
        $warningPercent = $diskThresholds['warningPercent'];
        $criticalPercent = $diskThresholds['criticalPercent'];

        $evidence = DiskEvidenceHelper::enrichEvidence($freePercent, $metricValue, $warningPercent, $criticalPercent);
        $title = DiskEvidenceHelper::formatTitle($freePercent, $metricValue);

        if ($freePercent >= $warningPercent) {
            $this->resolveCheckType($site, Incident::CHECK_DISK_LOW);
        } elseif ($freePercent < $criticalPercent) {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_DISK_LOW,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_CRITICAL,
                $title,
                $evidence,
            );
        } else {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_DISK_LOW,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_WARNING,
                $title,
                $evidence,
            );
        }

        $this->siteStatusResolver->sync($site);
    }

    /** @param array<string, mixed> $metricValue */
    public function onBackupMetric(Site $site, array $metricValue): void
    {
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        if ($this->skipDisabledCheck($site, Incident::CHECK_BACKUP_STALE)) {
            return;
        }

        $tags = is_array($metricValue['tags'] ?? null) ? $metricValue['tags'] : [];
        $backupStatus = is_string($tags['status'] ?? null) ? $tags['status'] : 'unknown';
        $ageHours = is_numeric($metricValue['value'] ?? null) ? (float) $metricValue['value'] : null;

        $backupThresholds = $this->checkThresholdResolver->backup($site);
        $warningHours = $backupThresholds['warningHours'];
        $criticalHours = $backupThresholds['criticalHours'];

        $evidence = [
            'ageHours' => $ageHours,
            'backupStatus' => $backupStatus,
            'lastBackupAt' => $tags['lastBackupAt'] ?? null,
            'lastBackupName' => $tags['lastBackupName'] ?? null,
            'warningHours' => $warningHours,
            'criticalHours' => $criticalHours,
            'metric' => $metricValue,
        ];

        if ($backupStatus === 'missing' || $ageHours === null) {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_BACKUP_STALE,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_WARNING,
                'Резервная копия не обнаружена',
                $evidence,
            );
            $this->siteStatusResolver->sync($site);

            return;
        }

        if ($ageHours < $warningHours) {
            $this->resolveCheckType($site, Incident::CHECK_BACKUP_STALE);
        } elseif ($ageHours >= $criticalHours) {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_BACKUP_STALE,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_CRITICAL,
                sprintf(
                    'Резервная копия устарела (%s)',
                    IncidentNotificationFormatter::formatAgeHours($ageHours),
                ),
                $evidence,
            );
        } else {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_BACKUP_STALE,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_WARNING,
                sprintf(
                    'Резервная копия устарела (%s)',
                    IncidentNotificationFormatter::formatAgeHours($ageHours),
                ),
                $evidence,
            );
        }

        $this->siteStatusResolver->sync($site);
    }

    /**
     * @param array<string, array<string, mixed>> $metrics keyed by metric name
     */
    public function onAgentsMetrics(Site $site, array $metrics): void
    {
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        if ($this->skipDisabledCheck($site, Incident::CHECK_AGENTS_LAG)) {
            return;
        }

        $maxLagSeconds = $this->metricIntValue($metrics['agents.max_lag_seconds'] ?? null);
        $overdueCount = $this->metricIntValue($metrics['agents.overdue_count'] ?? null);
        $activeCount = $this->metricIntValue($metrics['agents.active_count'] ?? null);

        $agentsThresholds = $this->checkThresholdResolver->agents($site);
        $warningLagSeconds = $agentsThresholds['warningLagSeconds'];
        $criticalLagSeconds = $agentsThresholds['criticalLagSeconds'];

        $overdueMetric = $metrics['agents.overdue_count'] ?? null;
        $tags = is_array($overdueMetric) && is_array($overdueMetric['tags'] ?? null)
            ? $overdueMetric['tags']
            : [];
        $stuckAgents = $tags['stuckAgents'] ?? [];

        $evidence = [
            'activeCount' => $activeCount,
            'overdueCount' => $overdueCount,
            'maxLagSeconds' => $maxLagSeconds,
            'warningLagSeconds' => $warningLagSeconds,
            'criticalLagSeconds' => $criticalLagSeconds,
            'stuckAgents' => is_array($stuckAgents) ? $stuckAgents : [],
            'metrics' => $metrics,
        ];

        if ($maxLagSeconds < $warningLagSeconds && $overdueCount === 0) {
            $this->resolveCheckType($site, Incident::CHECK_AGENTS_LAG);
            $this->siteStatusResolver->sync($site);

            return;
        }

        $severity = $maxLagSeconds >= $criticalLagSeconds
            ? Incident::SEVERITY_CRITICAL
            : Incident::SEVERITY_WARNING;

        $title = IncidentNotificationFormatter::formatAgentsIncidentTitle(
            $overdueCount,
            $maxLagSeconds,
            $evidence,
        );

        $this->openOrUpdateIncident(
            $site,
            Incident::CHECK_AGENTS_LAG,
            self::FINGERPRINT_DEFAULT,
            $severity,
            $title,
            $evidence,
        );

        $this->siteStatusResolver->sync($site);
    }

    /** @param array<string, mixed> $metricValue */
    public function onModulesUpdatesMetric(Site $site, array $metricValue): void
    {
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        if ($this->skipDisabledCheck($site, Incident::CHECK_MODULES_UPDATES)) {
            return;
        }

        $tags = is_array($metricValue['tags'] ?? null) ? $metricValue['tags'] : [];
        $status = is_string($tags['status'] ?? null) ? $tags['status'] : 'unknown';
        $updatesCount = is_numeric($metricValue['value'] ?? null) ? (int) $metricValue['value'] : null;

        if ($status === 'unknown' || $updatesCount === null) {
            return;
        }

        $warningUpdatesCount = $this->checkThresholdResolver->modulesWarningCount($site);
        $evidence = [
            'updatesAvailableCount' => $updatesCount,
            'warningUpdatesCount' => $warningUpdatesCount,
            'metric' => $metricValue,
        ];

        if ($updatesCount < $warningUpdatesCount) {
            $this->resolveCheckType($site, Incident::CHECK_MODULES_UPDATES);
            $this->siteStatusResolver->sync($site);

            return;
        }

        $this->openOrUpdateIncident(
            $site,
            Incident::CHECK_MODULES_UPDATES,
            self::FINGERPRINT_DEFAULT,
            Incident::SEVERITY_WARNING,
            sprintf('Доступно обновлений модулей: %d', $updatesCount),
            $evidence,
        );

        $this->siteStatusResolver->sync($site);
    }

    /** @param array<string, mixed> $metricValue */
    public function onBitrixLicenseMetric(Site $site, array $metricValue): void
    {
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        if ($this->skipDisabledCheck($site, Incident::CHECK_BITRIX_LICENSE_EXPIRY)) {
            return;
        }

        $tags = is_array($metricValue['tags'] ?? null) ? $metricValue['tags'] : [];
        $status = is_string($tags['status'] ?? null) ? $tags['status'] : 'unknown';

        if ($status === 'unknown') {
            return;
        }

        if ($status === 'unlimited') {
            $this->resolveCheckType($site, Incident::CHECK_BITRIX_LICENSE_EXPIRY);
            $this->siteStatusResolver->sync($site);

            return;
        }

        $daysLeft = is_numeric($metricValue['value'] ?? null) ? (int) $metricValue['value'] : null;
        if ($daysLeft === null) {
            return;
        }

        $thresholds = $this->checkThresholdResolver->bitrixLicense($site);
        $warningDays = $thresholds['warningDays'];
        $criticalDays = $thresholds['criticalDays'];

        $evidence = [
            'daysLeft' => $daysLeft,
            'warningDays' => $warningDays,
            'criticalDays' => $criticalDays,
            'source' => $tags['source'] ?? null,
            'edition' => $tags['edition'] ?? null,
            'isDemo' => $tags['isDemo'] ?? null,
            'productExpireDate' => $tags['productExpireDate'] ?? null,
            'supportExpireDate' => $tags['supportExpireDate'] ?? null,
            'productDaysLeft' => $tags['productDaysLeft'] ?? null,
            'supportDaysLeft' => $tags['supportDaysLeft'] ?? null,
            'metric' => $metricValue,
        ];

        if ($daysLeft >= $warningDays) {
            $this->resolveCheckType($site, Incident::CHECK_BITRIX_LICENSE_EXPIRY);
        } elseif ($daysLeft < $criticalDays) {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_BITRIX_LICENSE_EXPIRY,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_CRITICAL,
                IncidentNotificationFormatter::formatBitrixLicenseIncidentTitle($daysLeft, $evidence, Incident::SEVERITY_CRITICAL),
                $evidence,
            );
        } else {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_BITRIX_LICENSE_EXPIRY,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_WARNING,
                IncidentNotificationFormatter::formatBitrixLicenseIncidentTitle($daysLeft, $evidence, Incident::SEVERITY_WARNING),
                $evidence,
            );
        }

        $this->siteStatusResolver->sync($site);
    }

    public function evaluateSite(Site $site): void
    {
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        $this->evaluateHeartbeatMissing($site);
        $this->siteStatusResolver->sync($site);
    }

    private function evaluateUptimeResult(Check $check, CheckResult $result): void
    {
        $site = $check->getSite();
        $evidence = $result->getValueJson();
        $evidence['probeId'] = $result->getProbeId();
        $evidence['checkedAt'] = $result->getCheckedAt()->format(DATE_ATOM);

        if ($result->getStatus() === CheckResult::STATUS_OK) {
            $this->resolveCheckType($site, Incident::CHECK_UPTIME_HTTP);

            return;
        }

        $failures = $result->getConsecutiveFailures();
        $severity = $failures >= 2 ? Incident::SEVERITY_CRITICAL : Incident::SEVERITY_WARNING;
        $title = $this->formatUptimeIncidentTitle($evidence, $severity);

        $this->openOrUpdateIncident(
            $site,
            Incident::CHECK_UPTIME_HTTP,
            self::FINGERPRINT_DEFAULT,
            $severity,
            $title,
            $evidence,
        );
    }

    private function evaluateSslResult(Check $check, CheckResult $result): void
    {
        $site = $check->getSite();
        $evidence = $result->getValueJson();
        $evidence['probeId'] = $result->getProbeId();
        $evidence['checkedAt'] = $result->getCheckedAt()->format(DATE_ATOM);

        if ($result->getStatus() === CheckResult::STATUS_OK) {
            $this->resolveCheckType($site, Incident::CHECK_SSL_EXPIRY);

            return;
        }

        $severity = $result->getStatus() === CheckResult::STATUS_CRITICAL
            ? Incident::SEVERITY_CRITICAL
            : Incident::SEVERITY_WARNING;

        $daysLeft = $evidence['daysLeft'] ?? null;
        $title = is_int($daysLeft)
            ? sprintf('SSL истекает через %d дн.', $daysLeft)
            : 'Проблема SSL-сертификата';

        if (isset($evidence['error'])) {
            $title = 'SSL: не удалось проверить сертификат';
        }

        $this->openOrUpdateIncident(
            $site,
            Incident::CHECK_SSL_EXPIRY,
            self::FINGERPRINT_DEFAULT,
            $severity,
            $title,
            $evidence,
        );
    }

    private function evaluateDomainResult(Check $check, CheckResult $result): void
    {
        if ($result->getStatus() === CheckResult::STATUS_UNKNOWN) {
            return;
        }

        $site = $check->getSite();
        $evidence = $result->getValueJson();
        $evidence['probeId'] = $result->getProbeId();
        $evidence['checkedAt'] = $result->getCheckedAt()->format(DATE_ATOM);

        if ($result->getStatus() === CheckResult::STATUS_OK) {
            $this->resolveCheckType($site, Incident::CHECK_DOMAIN_EXPIRY);

            return;
        }

        $severity = $result->getStatus() === CheckResult::STATUS_CRITICAL
            ? Incident::SEVERITY_CRITICAL
            : Incident::SEVERITY_WARNING;

        $daysLeft = $evidence['daysLeft'] ?? null;
        $domain = $evidence['domain'] ?? $check->getSite()->getDomain();
        $title = is_int($daysLeft)
            ? sprintf('Домен %s истекает через %d дн.', $domain, $daysLeft)
            : sprintf('Срок домена %s под угрозой', $domain);

        $this->openOrUpdateIncident(
            $site,
            Incident::CHECK_DOMAIN_EXPIRY,
            self::FINGERPRINT_DEFAULT,
            $severity,
            $title,
            $evidence,
        );
    }

    private function evaluateHeartbeatMissing(Site $site): void
    {
        if ($this->skipDisabledCheck($site, Incident::CHECK_HEARTBEAT_MISSING)) {
            return;
        }

        $referenceAt = $site->getLastHeartbeatAt() ?? $site->getCreatedAt();
        $secondsSince = (new \DateTimeImmutable())->getTimestamp() - $referenceAt->getTimestamp();

        if ($secondsSince < $this->heartbeatWarningSeconds) {
            return;
        }

        $severity = $secondsSince >= $this->heartbeatCriticalSeconds
            ? Incident::SEVERITY_CRITICAL
            : Incident::SEVERITY_WARNING;

        $evidence = [
            'secondsSinceLastHeartbeat' => $secondsSince,
            'lastHeartbeatAt' => $site->getLastHeartbeatAt()?->format(DATE_ATOM),
            'referenceAt' => $referenceAt->format(DATE_ATOM),
            'warningThresholdSeconds' => $this->heartbeatWarningSeconds,
            'criticalThresholdSeconds' => $this->heartbeatCriticalSeconds,
        ];

        $title = $severity === Incident::SEVERITY_CRITICAL
            ? sprintf('Нет связи с модулем более %d мин', (int) round($this->heartbeatCriticalSeconds / 60))
            : sprintf('Нет связи с модулем более %d мин', (int) round($this->heartbeatWarningSeconds / 60));

        $this->openOrUpdateIncident(
            $site,
            Incident::CHECK_HEARTBEAT_MISSING,
            self::FINGERPRINT_DEFAULT,
            $severity,
            $title,
            $evidence,
        );
    }

    /** @param array<string, mixed> $evidence */
    private function formatUptimeIncidentTitle(array $evidence, string $severity): string
    {
        $httpStatus = $evidence['httpStatus'] ?? null;
        if (is_int($httpStatus) && $httpStatus > 0) {
            $prefix = $severity === Incident::SEVERITY_CRITICAL
                ? 'Сайт недоступен (подтверждено)'
                : 'Сайт недоступен';

            return sprintf('%s: HTTP %d', $prefix, $httpStatus);
        }

        $error = $evidence['error'] ?? null;
        if (is_string($error) && $error !== '') {
            return 'Сайт недоступен: '.$error;
        }

        return $severity === Incident::SEVERITY_CRITICAL
            ? 'Сайт недоступен (подтверждённая ошибка uptime)'
            : 'Сайт недоступен (ошибка uptime)';
    }

    /** @param array<string, mixed>|null $metric */
    private function metricIntValue(?array $metric): int
    {
        if ($metric === null) {
            return 0;
        }

        $value = $metric['value'] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function skipDisabledCheck(Site $site, string $checkType): bool
    {
        if ($this->checkMonitoringGate->isEnabled($site, $checkType)) {
            return false;
        }

        $this->resolveCheckType($site, $checkType);
        $this->siteStatusResolver->sync($site);

        return true;
    }

    private function resolveCheckType(Site $site, string $checkType): void
    {
        $activeIncidents = $this->incidentRepository->findActiveBySiteAndCheckTypeAll($site, $checkType);

        foreach ($activeIncidents as $incident) {
            $incident->resolve();
            $this->entityManager->persist(new IncidentEvent(
                $incident,
                IncidentEvent::TYPE_RESOLVED,
                'Проблема устранена автоматически',
                ['checkType' => $checkType, 'reason' => 'recovered'],
            ));
        }
    }

    /** @param array<string, mixed> $evidence */
    private function openOrUpdateIncident(
        Site $site,
        string $checkType,
        string $fingerprint,
        string $severity,
        string $title,
        array $evidence,
    ): void {
        $existing = $this->incidentRepository->findActiveBySiteAndCheckType($site, $checkType, $fingerprint);

        if ($existing instanceof Incident) {
            $severityChanged = $existing->getSeverity() !== $severity;
            $existing->setSeverity($severity);
            $existing->setTitle($title);
            $existing->updateEvidence($evidence);

            $this->entityManager->persist(new IncidentEvent(
                $existing,
                IncidentEvent::TYPE_EVIDENCE_UPDATED,
                $severityChanged ? 'Серьёзность инцидента повышена' : 'Обновлены данные инцидента',
                ['severity' => $severity, 'evidence' => $evidence],
            ));

            return;
        }

        if ($this->maintenanceWindowService->suppressesNewIncidents($site, $checkType)) {
            return;
        }

        $incident = new Incident(
            $site->getOrganization(),
            $site,
            $checkType,
            $fingerprint,
            $severity,
            $title,
            $evidence,
        );

        $this->entityManager->persist($incident);
        $this->entityManager->persist(new IncidentEvent(
            $incident,
            IncidentEvent::TYPE_OPENED,
            'Инцидент открыт',
            ['severity' => $severity, 'evidence' => $evidence],
        ));

        $this->notificationDispatcher->dispatchIncidentOpened($incident);
    }
}

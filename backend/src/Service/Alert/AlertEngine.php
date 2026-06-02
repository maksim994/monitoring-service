<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Entity\Check;
use App\Entity\CheckResult;
use App\Entity\Incident;
use App\Entity\IncidentEvent;
use App\Entity\Site;
use App\Repository\IncidentRepository;
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

    public function onProbeResult(Check $check, CheckResult $result): void
    {
        $site = $check->getSite();
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        match ($check->getType()) {
            Check::TYPE_UPTIME_HTTP => $this->evaluateUptimeResult($check, $result),
            Check::TYPE_SSL_EXPIRY => $this->evaluateSslResult($check, $result),
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

        $warningPercent = 15.0;
        $criticalPercent = 5.0;

        $evidence = [
            'freePercent' => $freePercent,
            'warningPercent' => $warningPercent,
            'criticalPercent' => $criticalPercent,
            'metric' => $metricValue,
        ];

        if ($freePercent >= $warningPercent) {
            $this->resolveCheckType($site, Incident::CHECK_DISK_LOW);
        } elseif ($freePercent < $criticalPercent) {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_DISK_LOW,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_CRITICAL,
                sprintf('Свободно %.1f%% диска', $freePercent),
                $evidence,
            );
        } else {
            $this->openOrUpdateIncident(
                $site,
                Incident::CHECK_DISK_LOW,
                self::FINGERPRINT_DEFAULT,
                Incident::SEVERITY_WARNING,
                sprintf('Свободно %.1f%% диска', $freePercent),
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
        $title = $severity === Incident::SEVERITY_CRITICAL
            ? 'Сайт недоступен (подтверждённая ошибка uptime)'
            : 'Сайт недоступен (ошибка uptime)';

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
            $title = 'SSL handshake failed';
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

    private function evaluateHeartbeatMissing(Site $site): void
    {
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
            ? sprintf('Нет heartbeat более %d минут', (int) round($this->heartbeatCriticalSeconds / 60))
            : sprintf('Нет heartbeat более %d минут', (int) round($this->heartbeatWarningSeconds / 60));

        $this->openOrUpdateIncident(
            $site,
            Incident::CHECK_HEARTBEAT_MISSING,
            self::FINGERPRINT_DEFAULT,
            $severity,
            $title,
            $evidence,
        );
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

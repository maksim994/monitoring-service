<?php

declare(strict_types=1);

namespace App\Service\Check;

use App\Entity\Check;
use App\Entity\Site;
use App\Repository\CheckRepository;

final class CheckThresholdResolver
{
    public function __construct(
        private readonly CheckRepository $checkRepository,
    ) {
    }

    /** @return array{warningPercent: float, criticalPercent: float} */
    public function disk(Site $site): array
    {
        $settings = $this->settingsFor($site, Check::TYPE_DISK_LOW);

        return [
            'warningPercent' => $this->floatSetting($settings, 'warningPercent', 15.0),
            'criticalPercent' => $this->floatSetting($settings, 'criticalPercent', 5.0),
        ];
    }

    /** @return array{warningHours: float, criticalHours: float} */
    public function backup(Site $site): array
    {
        $settings = $this->settingsFor($site, Check::TYPE_BACKUP_STALE);

        return [
            'warningHours' => $this->floatSetting($settings, 'warningHours', 72.0),
            'criticalHours' => $this->floatSetting($settings, 'criticalHours', 168.0),
        ];
    }

    /** @return array{warningLagSeconds: int, criticalLagSeconds: int} */
    public function agents(Site $site): array
    {
        $settings = $this->settingsFor($site, Check::TYPE_AGENTS_LAG);

        return [
            'warningLagSeconds' => $this->intSetting($settings, 'warningLagSeconds', 1800),
            'criticalLagSeconds' => $this->intSetting($settings, 'criticalLagSeconds', 7200),
        ];
    }

    public function modulesWarningCount(Site $site): int
    {
        $settings = $this->settingsFor($site, Check::TYPE_MODULES_UPDATES);

        return $this->intSetting($settings, 'warningUpdatesCount', 1);
    }

    /** @return array<string, mixed> */
    private function settingsFor(Site $site, string $type): array
    {
        $check = $this->checkRepository->findBySiteAndType($site, $type);

        return $check?->getSettingsJson() ?? [];
    }

    /** @param array<string, mixed> $settings */
    private function floatSetting(array $settings, string $key, float $default): float
    {
        $value = $settings[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    /** @param array<string, mixed> $settings */
    private function intSetting(array $settings, string $key, int $default): int
    {
        $value = $settings[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}

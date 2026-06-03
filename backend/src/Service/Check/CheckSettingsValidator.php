<?php

declare(strict_types=1);

namespace App\Service\Check;

use App\Entity\Check;

final class CheckSettingsValidator
{
    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $patch
     *
     * @return array<string, mixed>
     */
    public function merge(string $type, array $current, array $patch): array
    {
        $allowed = match ($type) {
            Check::TYPE_DISK_LOW => ['warningPercent', 'criticalPercent'],
            Check::TYPE_BACKUP_STALE => ['warningHours', 'criticalHours'],
            Check::TYPE_AGENTS_LAG => ['warningLagSeconds', 'criticalLagSeconds'],
            Check::TYPE_SSL_EXPIRY, Check::TYPE_DOMAIN_EXPIRY, Check::TYPE_BITRIX_LICENSE_EXPIRY => ['warningDays', 'criticalDays'],
            Check::TYPE_MODULES_UPDATES => ['warningUpdatesCount'],
            default => [],
        };

        if ($allowed === []) {
            throw new \InvalidArgumentException('Для этой проверки пороги не настраиваются.');
        }

        $merged = $current;
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $patch)) {
                continue;
            }

            $merged[$key] = $patch[$key];
        }

        $this->assertValid($type, $merged);

        return $merged;
    }

    /** @param array<string, mixed> $settings */
    private function assertValid(string $type, array $settings): void
    {
        match ($type) {
            Check::TYPE_DISK_LOW => $this->assertDisk($settings),
            Check::TYPE_BACKUP_STALE => $this->assertBackup($settings),
            Check::TYPE_AGENTS_LAG => $this->assertAgents($settings),
            Check::TYPE_SSL_EXPIRY, Check::TYPE_DOMAIN_EXPIRY, Check::TYPE_BITRIX_LICENSE_EXPIRY => $this->assertDays($settings),
            Check::TYPE_MODULES_UPDATES => $this->assertModules($settings),
            default => throw new \InvalidArgumentException('Неизвестный тип проверки.'),
        };
    }

    /** @param array<string, mixed> $settings */
    private function assertDisk(array $settings): void
    {
        $warning = $this->requireNumeric($settings, 'warningPercent');
        $critical = $this->requireNumeric($settings, 'criticalPercent');

        if ($warning <= 0 || $warning > 90) {
            throw new \InvalidArgumentException('Порог предупреждения для диска: от 1 до 90%.');
        }

        if ($critical <= 0 || $critical > 50) {
            throw new \InvalidArgumentException('Критический порог для диска: от 1 до 50%.');
        }

        if ($critical >= $warning) {
            throw new \InvalidArgumentException('Критический порог диска должен быть меньше порога предупреждения.');
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertBackup(array $settings): void
    {
        $warning = $this->requireNumeric($settings, 'warningHours');
        $critical = $this->requireNumeric($settings, 'criticalHours');

        if ($warning < 1 || $warning > 24 * 90) {
            throw new \InvalidArgumentException('Порог предупреждения для бэкапа: от 1 до 2160 ч.');
        }

        if ($critical < 1 || $critical > 24 * 365) {
            throw new \InvalidArgumentException('Критический порог для бэкапа: от 1 до 8760 ч.');
        }

        if ($critical <= $warning) {
            throw new \InvalidArgumentException('Критический порог бэкапа должен быть больше порога предупреждения.');
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertAgents(array $settings): void
    {
        $warning = $this->requireNumeric($settings, 'warningLagSeconds');
        $critical = $this->requireNumeric($settings, 'criticalLagSeconds');

        if ($warning < 60 || $warning > 86400 * 30) {
            throw new \InvalidArgumentException('Порог предупреждения для agents: от 1 мин до 30 сут.');
        }

        if ($critical < 60 || $critical > 86400 * 365) {
            throw new \InvalidArgumentException('Критический порог для agents: от 1 мин до 365 сут.');
        }

        if ($critical <= $warning) {
            throw new \InvalidArgumentException('Критический порог agents должен быть больше порога предупреждения.');
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertDays(array $settings): void
    {
        $warning = $this->requireNumeric($settings, 'warningDays');
        $critical = $this->requireNumeric($settings, 'criticalDays');

        if ($warning < 1 || $warning > 365) {
            throw new \InvalidArgumentException('Порог предупреждения: от 1 до 365 дней.');
        }

        if ($critical < 1 || $critical > 90) {
            throw new \InvalidArgumentException('Критический порог: от 1 до 90 дней.');
        }

        if ($critical >= $warning) {
            throw new \InvalidArgumentException('Критический порог должен быть меньше порога предупреждения (дней до истечения).');
        }
    }

    /** @param array<string, mixed> $settings */
    private function assertModules(array $settings): void
    {
        $count = $this->requireNumeric($settings, 'warningUpdatesCount');

        if ($count < 1 || $count > 100) {
            throw new \InvalidArgumentException('Порог обновлений модулей: от 1 до 100.');
        }
    }

    /** @param array<string, mixed> $settings */
    private function requireNumeric(array $settings, string $key): float
    {
        if (!isset($settings[$key]) || !is_numeric($settings[$key])) {
            throw new \InvalidArgumentException(sprintf('Поле %s обязательно.', $key));
        }

        return (float) $settings[$key];
    }
}

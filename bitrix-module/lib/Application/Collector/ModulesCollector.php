<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

use Bitrix\Main\Config\Option;

final class ModulesCollector
{
    private const MODULE_ID = 'vendor.monitoring';
    private const CACHE_TTL_SECONDS = 43200;

    /** @return list<array<string, mixed>> */
    public function collect(): array
    {
        if (!class_exists(\CModule::class)) {
            return [];
        }

        $installedCount = $this->countInstalledModules();
        $updatesCount = $this->resolveUpdatesCount();

        $metrics = [
            [
                'key' => 'modules.installed_count',
                'value' => $installedCount,
                'unit' => 'count',
                'tags' => ['status' => 'ok'],
            ],
        ];

        if ($updatesCount === null) {
            $metrics[] = [
                'key' => 'modules.updates_available_count',
                'value' => null,
                'unit' => 'count',
                'tags' => ['status' => 'unknown'],
            ];
        } else {
            $metrics[] = [
                'key' => 'modules.updates_available_count',
                'value' => $updatesCount,
                'unit' => 'count',
                'tags' => ['status' => 'ok'],
            ];
        }

        return $metrics;
    }

    private function countInstalledModules(): int
    {
        $count = 0;
        $result = \CModule::GetList();
        if (!is_object($result)) {
            return 0;
        }

        while ($module = $result->Fetch()) {
            if (!is_array($module)) {
                continue;
            }

            if (($module['IsInstalled'] ?? '') === 'Y') {
                ++$count;
            }
        }

        return $count;
    }

    private function resolveUpdatesCount(): ?int
    {
        $lastCheck = (int) Option::get(self::MODULE_ID, 'modules_updates_checked_at', '0');
        $cached = Option::get(self::MODULE_ID, 'modules_updates_count', '');

        if ($lastCheck > 0 && (time() - $lastCheck) < self::CACHE_TTL_SECONDS && $cached !== '') {
            return (int) $cached;
        }

        $fresh = $this->fetchUpdatesCount();
        if ($fresh === null) {
            return null;
        }

        Option::set(self::MODULE_ID, 'modules_updates_checked_at', (string) time());
        Option::set(self::MODULE_ID, 'modules_updates_count', (string) $fresh);

        return $fresh;
    }

    private function fetchUpdatesCount(): ?int
    {
        $clientPath = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/').'/bitrix/modules/main/classes/general/update_client.php';
        if (!is_file($clientPath)) {
            return null;
        }

        require_once $clientPath;

        if (!class_exists(\CUpdateClient::class)) {
            return null;
        }

        try {
            $list = \CUpdateClient::GetUpdatesList([], 'ru', 'Y');
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($list)) {
            return 0;
        }

        return $this->countUpdatesInList($list);
    }

    /** @param array<string, mixed> $list */
    private function countUpdatesInList(array $list): int
    {
        if (!isset($list['MODULE'])) {
            return 0;
        }

        $modules = $list['MODULE'];
        if (!is_array($modules)) {
            return 0;
        }

        if ($this->isListArray($modules)) {
            return count($modules);
        }

        return 1;
    }

    /** @param array<mixed> $value */
    private function isListArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}

<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

final class BackupCollector
{
    /** @return list<array<string, mixed>> */
    public function collect(): array
    {
        $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($documentRoot === '') {
            return [];
        }

        $backupDir = rtrim($documentRoot, '/').'/bitrix/backup';
        if (!is_dir($backupDir)) {
            return [
                $this->metric(null, 'missing', null),
            ];
        }

        $latestMtime = null;
        foreach (['*.tar', '*.tar.gz', '*.tar.bz2', '*.tgz'] as $pattern) {
            foreach (glob($backupDir.'/'.$pattern) ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }

                $mtime = filemtime($path);
                if ($mtime === false) {
                    continue;
                }

                if ($latestMtime === null || $mtime > $latestMtime) {
                    $latestMtime = $mtime;
                }
            }
        }

        if ($latestMtime === null) {
            return [
                $this->metric(null, 'missing', null),
            ];
        }

        $ageHours = round((time() - $latestMtime) / 3600, 2);

        return [
            $this->metric($ageHours, 'ok', $latestMtime),
        ];
    }

    /** @return array<string, mixed> */
    private function metric(?float $ageHours, string $status, ?int $lastBackupTimestamp): array
    {
        $tags = ['status' => $status];
        if ($lastBackupTimestamp !== null) {
            $tags['lastBackupAt'] = gmdate('c', $lastBackupTimestamp);
        }

        return [
            'key' => 'backup.age_hours',
            'value' => $ageHours,
            'unit' => 'hours',
            'tags' => $tags,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

final class BackupCollector
{
    /**
     * Bitrix: монолитный архив (.tar.gz) или многотомный (.tar.gz.1 … .tar.gz.N).
     */
    private const BACKUP_FILE_PATTERN = '/\.(?:tar(?:\.(?:gz|bz2))?|tgz)(?:\.\d+)?$/i';

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
                $this->metric(null, 'missing', null, null),
            ];
        }

        $latest = $this->findLatestBackup($backupDir);
        if ($latest === null) {
            return [
                $this->metric(null, 'missing', null, null),
            ];
        }

        $ageHours = round((time() - $latest['mtime']) / 3600, 2);

        return [
            $this->metric($ageHours, 'ok', $latest['mtime'], $latest['label']),
        ];
    }

    /**
     * @return array{mtime: int, label: string}|null
     */
    private function findLatestBackup(string $backupDir): ?array
    {
        $entries = scandir($backupDir);
        if ($entries === false) {
            return null;
        }

        /** @var array<string, list<int>> $groups keyed by logical archive name */
        $groups = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!preg_match(self::BACKUP_FILE_PATTERN, $entry)) {
                continue;
            }

            $path = $backupDir.'/'.$entry;
            if (!is_file($path)) {
                continue;
            }

            $mtime = filemtime($path);
            if ($mtime === false) {
                continue;
            }

            $baseName = $this->normalizeBackupBaseName($entry);
            $groups[$baseName][] = $mtime;
        }

        if ($groups === []) {
            return null;
        }

        $bestMtime = null;
        $bestLabel = null;

        foreach ($groups as $baseName => $mtimes) {
            $groupMtime = max($mtimes);
            if ($bestMtime === null || $groupMtime > $bestMtime) {
                $bestMtime = $groupMtime;
                $bestLabel = $baseName;
            }
        }

        if ($bestMtime === null || $bestLabel === null) {
            return null;
        }

        return [
            'mtime' => $bestMtime,
            'label' => $bestLabel,
        ];
    }

    /**
     * 20260603_101229_full_xxx.tar.gz.7 → 20260603_101229_full_xxx.tar.gz
     */
    private function normalizeBackupBaseName(string $filename): string
    {
        if (preg_match('/^(.+\.(?:tar(?:\.(?:gz|bz2))?|tgz))(?:\.\d+)?$/i', $filename, $matches)) {
            return $matches[1];
        }

        return $filename;
    }

    /** @return array<string, mixed> */
    private function metric(?float $ageHours, string $status, ?int $lastBackupTimestamp, ?string $backupName): array
    {
        $tags = ['status' => $status];
        if ($lastBackupTimestamp !== null) {
            $tags['lastBackupAt'] = gmdate('c', $lastBackupTimestamp);
        }
        if (is_string($backupName) && $backupName !== '') {
            $tags['lastBackupName'] = $backupName;
        }

        return [
            'key' => 'backup.age_hours',
            'value' => $ageHours,
            'unit' => 'hours',
            'tags' => $tags,
        ];
    }
}

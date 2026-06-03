<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

final class BackupCollector
{
    /**
     * Форматы Bitrix (main/tools/backup.php):
     * - .tar / .tar.gz / .tar.bz2
     * - .enc / .enc.gz (шифрование)
     * - многотомность: .tar.gz.1 … .tar.gz.N (то же для .enc.gz)
     */
    private const BACKUP_BASE_PATTERN = '/^(.+\.(?:tar(?:\.(?:gz|bz2))?|tgz|enc(?:\.gz)?))(?:\.\d+)?$/i';

    /** @return list<array<string, mixed>> */
    public function collect(): array
    {
        $scan = $this->scan();

        if (!$scan['dirExists']) {
            return [
                $this->metric(null, 'missing', null, null),
            ];
        }

        if ($scan['selected'] === null) {
            return [
                $this->metric(null, 'missing', null, null),
            ];
        }

        $selected = $scan['selected'];

        return [
            $this->metric(
                $selected['ageHours'],
                'ok',
                $selected['mtime'],
                $selected['baseName'],
            ),
        ];
    }

    /**
     * Диагностика для страницы настроек модуля: что видит collector на диске.
     *
     * @return array{
     *   documentRoot: string,
     *   backupDir: string,
     *   dirExists: bool,
     *   collector: string,
     *   scannedFiles: int,
     *   selected: ?array{baseName: string, mtime: int, dateLocal: string, ageHours: float},
     *   groups: list<array{baseName: string, partsCount: int, newestMtime: int, dateLocal: string, ageHours: float, parts: list<string>}>,
     *   metric: ?array<string, mixed>
     * }
     */
    public function inspect(): array
    {
        $scan = $this->scan();

        if (!$scan['dirExists'] || $scan['selected'] === null) {
            $metric = $this->metric(null, 'missing', null, null);
        } else {
            $selected = $scan['selected'];
            $metric = $this->metric(
                $selected['ageHours'],
                'ok',
                $selected['mtime'],
                $selected['baseName'],
            );
        }

        return [
            'documentRoot' => $scan['documentRoot'],
            'backupDir' => $scan['backupDir'],
            'dirExists' => $scan['dirExists'],
            'collector' => 'backup_v2',
            'scannedFiles' => $scan['scannedFiles'],
            'selected' => $scan['selected'],
            'groups' => $scan['groups'],
            'metric' => $metric,
        ];
    }

    /**
     * @return array{
     *   documentRoot: string,
     *   backupDir: string,
     *   dirExists: bool,
     *   scannedFiles: int,
     *   selected: ?array{baseName: string, mtime: int, dateLocal: string, ageHours: float},
     *   groups: list<array{baseName: string, partsCount: int, newestMtime: int, dateLocal: string, ageHours: float, parts: list<string>}>
     * }
     */
    private function scan(): array
    {
        $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        $backupDir = $documentRoot !== '' ? rtrim($documentRoot, '/').'/bitrix/backup' : '';
        $dirExists = $backupDir !== '' && is_dir($backupDir);

        $empty = [
            'documentRoot' => $documentRoot,
            'backupDir' => $backupDir,
            'dirExists' => $dirExists,
            'scannedFiles' => 0,
            'selected' => null,
            'groups' => [],
        ];

        if (!$dirExists) {
            return $empty;
        }

        /** @var array<string, list<array{name: string, mtime: int}>> $rawGroups */
        $rawGroups = [];
        $scannedFiles = 0;

        foreach ($this->iterBackupFiles($backupDir) as $file) {
            ++$scannedFiles;
            $baseName = $this->normalizeBackupBaseName($file['name']);
            $rawGroups[$baseName][] = $file;
        }

        if ($rawGroups === []) {
            return [
                ...$empty,
                'scannedFiles' => 0,
            ];
        }

        $groups = [];
        $bestMtime = null;
        $bestBaseName = null;

        foreach ($rawGroups as $baseName => $parts) {
            $newestMtime = 0;
            $partNames = [];

            foreach ($parts as $part) {
                $newestMtime = max($newestMtime, $part['mtime']);
                $partNames[] = $part['name'];
            }

            sort($partNames);

            if ($bestMtime === null || $newestMtime > $bestMtime) {
                $bestMtime = $newestMtime;
                $bestBaseName = $baseName;
            }

            $groups[] = [
                'baseName' => $baseName,
                'partsCount' => count($parts),
                'newestMtime' => $newestMtime,
                'dateLocal' => $this->formatLocalDateTime($newestMtime),
                'ageHours' => round((time() - $newestMtime) / 3600, 2),
                'parts' => $partNames,
            ];
        }

        usort(
            $groups,
            static fn (array $a, array $b): int => $b['newestMtime'] <=> $a['newestMtime'],
        );

        $selected = null;
        if ($bestMtime !== null && $bestBaseName !== null) {
            $selected = [
                'baseName' => $bestBaseName,
                'mtime' => $bestMtime,
                'dateLocal' => $this->formatLocalDateTime($bestMtime),
                'ageHours' => round((time() - $bestMtime) / 3600, 2),
            ];
        }

        return [
            'documentRoot' => $documentRoot,
            'backupDir' => $backupDir,
            'dirExists' => true,
            'scannedFiles' => $scannedFiles,
            'selected' => $selected,
            'groups' => $groups,
        ];
    }

    private function formatLocalDateTime(int $timestamp): string
    {
        return date('d.m.Y H:i:s', $timestamp);
    }

    /**
     * @return iterable<int, array{name: string, mtime: int}>
     */
    private function iterBackupFiles(string $rootDir): iterable
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $rootDir,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS,
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        $iterator->setMaxDepth(2);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $fileInfo->getPathname());
            if (preg_match('#/bitrix/backup/clouds(/|$)#', $path)) {
                continue;
            }

            $name = $fileInfo->getFilename();
            if (!$this->isBackupArchiveFile($name)) {
                continue;
            }

            $mtime = $fileInfo->getMTime();

            yield [
                'name' => $name,
                'mtime' => $mtime,
            ];
        }
    }

    private function isBackupArchiveFile(string $filename): bool
    {
        $lower = strtolower($filename);
        if (in_array($lower, ['index.php', '.htaccess', '.access.php'], true)) {
            return false;
        }

        if (str_ends_with($lower, '.sql') || str_ends_with($lower, '.log')) {
            return false;
        }

        return (bool) preg_match(self::BACKUP_BASE_PATTERN, $filename);
    }

    private function normalizeBackupBaseName(string $filename): string
    {
        if (preg_match(self::BACKUP_BASE_PATTERN, $filename, $matches)) {
            return $matches[1];
        }

        return $filename;
    }

    /** @return array<string, mixed> */
    private function metric(?float $ageHours, string $status, ?int $lastBackupTimestamp, ?string $backupName): array
    {
        $tags = [
            'status' => $status,
            'collector' => 'backup_v2',
        ];
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

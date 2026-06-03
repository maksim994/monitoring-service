<?php

declare(strict_types=1);

namespace App\Service\Alert;

final class DiskEvidenceHelper
{
    /** @param array<string, mixed> $metricValue */
    public static function enrichEvidence(float $freePercent, array $metricValue, float $warningPercent, float $criticalPercent): array
    {
        $tags = is_array($metricValue['tags'] ?? null) ? $metricValue['tags'] : [];
        $path = is_string($tags['path'] ?? null) ? $tags['path'] : null;
        $totalBytes = self::intOrNull($tags['totalBytes'] ?? null);
        $freeBytes = self::intOrNull($tags['freeBytes'] ?? null);
        $usedBytes = self::intOrNull($tags['usedBytes'] ?? null);

        return [
            'freePercent' => $freePercent,
            'warningPercent' => $warningPercent,
            'criticalPercent' => $criticalPercent,
            'path' => $path,
            'totalBytes' => $totalBytes,
            'freeBytes' => $freeBytes,
            'usedBytes' => $usedBytes,
            'metric' => $metricValue,
        ];
    }

    /** @param array<string, mixed> $metricValue */
    public static function formatTitle(float $freePercent, array $metricValue): string
    {
        $tags = is_array($metricValue['tags'] ?? null) ? $metricValue['tags'] : [];
        $totalBytes = self::intOrNull($tags['totalBytes'] ?? null);
        $freeBytes = self::intOrNull($tags['freeBytes'] ?? null);
        $usedBytes = self::intOrNull($tags['usedBytes'] ?? null);

        if ($totalBytes !== null && $freeBytes !== null && $usedBytes !== null) {
            return sprintf(
                'Свободно %.1f%%: %s / %s (занято %s)',
                $freePercent,
                self::formatBytes($freeBytes),
                self::formatBytes($totalBytes),
                self::formatBytes($usedBytes),
            );
        }

        return sprintf('Свободно %.1f%% диска', $freePercent);
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_099_511_627_776) {
            return sprintf('%.1f TiB', $bytes / 1_099_511_627_776);
        }

        if ($bytes >= 1_073_741_824) {
            return sprintf('%.1f GiB', $bytes / 1_073_741_824);
        }

        if ($bytes >= 1_048_576) {
            return sprintf('%.1f MiB', $bytes / 1_048_576);
        }

        return sprintf('%d KiB', (int) round($bytes / 1024));
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}

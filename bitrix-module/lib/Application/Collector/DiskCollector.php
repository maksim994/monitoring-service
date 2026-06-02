<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

final class DiskCollector
{
    /** @return list<array<string, mixed>> */
    public function collect(): array
    {
        $paths = [
            $_SERVER['DOCUMENT_ROOT'] ?? '',
            dirname((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')),
        ];

        $metrics = [];
        foreach ($paths as $path) {
            if ($path === '' || !is_dir($path)) {
                continue;
            }

            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            if (!is_float($free) || !is_float($total) || $total <= 0) {
                continue;
            }

            $metrics[] = [
                'key' => 'disk.free_percent',
                'value' => round(($free / $total) * 100, 2),
                'unit' => 'percent',
                'tags' => ['path' => $path],
            ];
            break;
        }

        return $metrics;
    }
}

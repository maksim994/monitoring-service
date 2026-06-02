<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Collector;

final class EnvironmentCollector
{
    /** @return array<string, mixed> */
    public function collect(): array
    {
        return [
            'bitrixVersion' => defined('SM_VERSION') ? SM_VERSION : 'unknown',
            'phpVersion' => PHP_VERSION,
            'encoding' => defined('BX_UTF') && BX_UTF ? 'utf-8' : 'legacy',
            'timezone' => date_default_timezone_get(),
        ];
    }
}

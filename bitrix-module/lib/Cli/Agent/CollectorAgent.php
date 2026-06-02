<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Cli\Agent;

use Bitrix\Main\Loader;
use Vendor\Monitoring\Application\Collector\DiskCollector;
use Vendor\Monitoring\Application\Collector\EnvironmentCollector;
use Vendor\Monitoring\Application\Service\ModuleSender;

final class CollectorAgent
{
    public static function run(): string
    {
        if (!Loader::includeModule('vendor.monitoring')) {
            return '\\Vendor\\Monitoring\\Cli\\Agent\\CollectorAgent::run();';
        }

        $sender = new ModuleSender();
        $environment = (new EnvironmentCollector())->collect();
        $sender->sendHeartbeat($environment);

        $metrics = (new DiskCollector())->collect();
        if ($metrics !== []) {
            $sender->sendMetricsBatch($metrics);
        }

        $sender->flushQueue();

        return '\\Vendor\\Monitoring\\Cli\\Agent\\CollectorAgent::run();';
    }
}

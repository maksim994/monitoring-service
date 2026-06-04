<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Cli\Agent;

use Bitrix\Main\Loader;
use Vendor\Monitoring\Application\Service\MetricsPublisher;

final class CollectorAgent
{
    public static function run(): string
    {
        if (!Loader::includeModule('vendor.monitoring')) {
            return '\\Vendor\\Monitoring\\Cli\\Agent\\CollectorAgent::run();';
        }

        (new MetricsPublisher())->publishAll();

        return '\\Vendor\\Monitoring\\Cli\\Agent\\CollectorAgent::run();';
    }
}

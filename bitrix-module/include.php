<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vendor.monitoring', [
    'Vendor\\Monitoring\\Application\\Service\\ModuleSender' => 'lib/Application/Service/ModuleSender.php',
    'Vendor\\Monitoring\\Application\\Service\\MetricsPublisher' => 'lib/Application/Service/MetricsPublisher.php',
    'Vendor\\Monitoring\\Application\\Collector\\EnvironmentCollector' => 'lib/Application/Collector/EnvironmentCollector.php',
    'Vendor\\Monitoring\\Application\\Collector\\DiskCollector' => 'lib/Application/Collector/DiskCollector.php',
    'Vendor\\Monitoring\\Application\\Collector\\BackupCollector' => 'lib/Application/Collector/BackupCollector.php',
    'Vendor\\Monitoring\\Application\\Collector\\AgentsCollector' => 'lib/Application/Collector/AgentsCollector.php',
    'Vendor\\Monitoring\\Application\\Collector\\ModulesCollector' => 'lib/Application/Collector/ModulesCollector.php',
    'Vendor\\Monitoring\\Application\\Collector\\LicenseCollector' => 'lib/Application/Collector/LicenseCollector.php',
    'Vendor\\Monitoring\\Infrastructure\\Queue\\ModuleQueue' => 'lib/Infrastructure/Queue/ModuleQueue.php',
    'Vendor\\Monitoring\\Cli\\Agent\\CollectorAgent' => 'lib/Cli/Agent/CollectorAgent.php',
]);

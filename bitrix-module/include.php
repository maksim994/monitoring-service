<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('vendor.monitoring', [
    'Vendor\\Monitoring\\Application\\Service\\ModuleSender' => 'lib/Application/Service/ModuleSender.php',
    'Vendor\\Monitoring\\Application\\Collector\\EnvironmentCollector' => 'lib/Application/Collector/EnvironmentCollector.php',
    'Vendor\\Monitoring\\Application\\Collector\\DiskCollector' => 'lib/Application/Collector/DiskCollector.php',
    'Vendor\\Monitoring\\Infrastructure\\Queue\\ModuleQueue' => 'lib/Infrastructure/Queue/ModuleQueue.php',
    'Vendor\\Monitoring\\Cli\\Agent\\CollectorAgent' => 'lib/Cli/Agent/CollectorAgent.php',
]);

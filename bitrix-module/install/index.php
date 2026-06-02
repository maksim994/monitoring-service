<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

class vendor_monitoring extends CModule
{
    public $MODULE_ID = 'vendor.monitoring';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__.'/version.php';
        Loc::loadMessages(__FILE__);
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('VENDOR_MONITORING_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('VENDOR_MONITORING_MODULE_DESC');
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!$GLOBALS['USER']->IsAdmin()) {
            return;
        }

        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        if (!$GLOBALS['USER']->IsAdmin()) {
            return;
        }

        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallDB()
    {
        return true;
    }

    public function UnInstallDB()
    {
        return true;
    }

    public function InstallEvents()
    {
        CAgent::AddAgent(
            '\\Vendor\\Monitoring\\Cli\\Agent\\CollectorAgent::run();',
            $this->MODULE_ID,
            'N',
            300,
            '',
            'Y',
            ConvertTimeStamp(time() + 300, 'FULL')
        );

        return true;
    }

    public function UnInstallEvents()
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);

        return true;
    }

    public function InstallFiles()
    {
        CopyDirFiles(
            __DIR__.'/../admin',
            $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin',
            true,
            true,
            false,
            'vendor_monitoring_settings.php',
        );

        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(
            __DIR__.'/../admin',
            $_SERVER['DOCUMENT_ROOT'].'/bitrix/admin',
            false,
            'vendor_monitoring_settings.php',
        );

        return true;
    }
}

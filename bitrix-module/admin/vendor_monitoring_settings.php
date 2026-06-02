<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Vendor\Monitoring\Application\Service\ModuleSender;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED') ?: 'Access denied');
}

Loader::includeModule('vendor.monitoring');

$moduleId = 'vendor.monitoring';
$message = null;
$hasSecret = Option::get($moduleId, 'api_secret', '') !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Option::set($moduleId, 'mode', $_POST['mode'] ?? 'saas');
    Option::set($moduleId, 'api_url', trim($_POST['api_url'] ?? ''));
    Option::set($moduleId, 'site_id', trim($_POST['site_id'] ?? ''));

    $newSecret = trim($_POST['api_secret'] ?? '');
    if ($newSecret !== '') {
        Option::set($moduleId, 'api_secret', $newSecret);
        $hasSecret = true;
    }

    if (isset($_POST['test_heartbeat'])) {
        $environment = [
            'bitrixVersion' => defined('SM_VERSION') ? SM_VERSION : 'unknown',
            'phpVersion' => PHP_VERSION,
        ];
        $result = (new ModuleSender())->sendHeartbeat($environment);
        $message = ($result['success'] ?? false)
            ? Loc::getMessage('VENDOR_MONITORING_HEARTBEAT_OK', ['#STATUS#' => (string) ($result['status'] ?? '')])
            : Loc::getMessage('VENDOR_MONITORING_HEARTBEAT_FAIL', ['#STATUS#' => (string) ($result['status'] ?? '')]);
    } else {
        $message = Loc::getMessage('VENDOR_MONITORING_SAVED');
    }
}

$values = [
    'mode' => Option::get($moduleId, 'mode', 'saas'),
    'api_url' => Option::get($moduleId, 'api_url', ''),
    'site_id' => Option::get($moduleId, 'site_id', ''),
];

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
?>
<form method="post">
    <?= bitrix_sessid_post(); ?>
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_MODE')); ?></td>
            <td><input type="text" name="mode" value="<?= htmlspecialcharsbx($values['mode']); ?>"></td>
        </tr>
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_API_URL')); ?></td>
            <td><input type="text" name="api_url" value="<?= htmlspecialcharsbx($values['api_url']); ?>" size="60"></td>
        </tr>
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_SITE_ID')); ?></td>
            <td><input type="text" name="site_id" value="<?= htmlspecialcharsbx($values['site_id']); ?>" size="60"></td>
        </tr>
        <tr>
            <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_API_SECRET')); ?></td>
            <td>
                <input
                    type="password"
                    name="api_secret"
                    value=""
                    placeholder="<?= htmlspecialcharsbx($hasSecret ? '********' : ''); ?>"
                    size="60"
                    autocomplete="new-password"
                >
                <div class="adm-info-message-wrap" style="margin-top:8px;">
                    <div class="adm-info-message"><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_SECRET_HINT')); ?></div>
                </div>
            </td>
        </tr>
    </table>
    <input type="submit" name="save" value="<?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_SAVE')); ?>">
    <input type="submit" name="test_heartbeat" value="<?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_TEST_HEARTBEAT')); ?>">
</form>
<?php if ($message): ?>
    <div class="adm-info-message-wrap" style="margin-top:12px;">
        <div class="adm-info-message"><?= htmlspecialcharsbx($message); ?></div>
    </div>
<?php endif; ?>
<?php
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';

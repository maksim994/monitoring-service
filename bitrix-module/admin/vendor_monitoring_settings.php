<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Vendor\Monitoring\Application\Collector\BackupCollector;
use Vendor\Monitoring\Application\Service\ModuleSender;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';

Loc::loadMessages(__FILE__);

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED') ?: 'Access denied');
}

Loader::includeModule('vendor.monitoring');

$moduleId = 'vendor.monitoring';
$message = null;
$messageType = 'ok';
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
        $messageType = ($result['success'] ?? false) ? 'ok' : 'error';
    } elseif (isset($_POST['test_backup_metrics'])) {
        $metrics = (new BackupCollector())->collect();
        $result = (new ModuleSender())->sendMetricsBatch($metrics);
        $inspect = (new BackupCollector())->inspect();
        $selected = $inspect['selected'];
        if ($result['success'] ?? false) {
            $message = $selected !== null
                ? Loc::getMessage(
                    'VENDOR_MONITORING_BACKUP_METRICS_OK',
                    [
                        '#STATUS#' => (string) ($result['status'] ?? ''),
                        '#NAME#' => (string) $selected['baseName'],
                        '#DATE#' => (string) $selected['dateLocal'],
                        '#HOURS#' => (string) $selected['ageHours'],
                    ],
                )
                : Loc::getMessage('VENDOR_MONITORING_BACKUP_METRICS_OK_EMPTY', ['#STATUS#' => (string) ($result['status'] ?? '')]);
            $messageType = 'ok';
        } else {
            $message = Loc::getMessage('VENDOR_MONITORING_BACKUP_METRICS_FAIL', ['#STATUS#' => (string) ($result['status'] ?? '')]);
            $messageType = 'error';
        }
    } else {
        $message = Loc::getMessage('VENDOR_MONITORING_SAVED');
    }
}

$values = [
    'mode' => Option::get($moduleId, 'mode', 'saas'),
    'api_url' => Option::get($moduleId, 'api_url', ''),
    'site_id' => Option::get($moduleId, 'site_id', ''),
];

$backupInspect = (new BackupCollector())->inspect();

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
    <input type="submit" name="test_backup_metrics" value="<?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_TEST_BACKUP_METRICS')); ?>">
</form>

<?php if ($message): ?>
    <div class="adm-info-message-wrap" style="margin-top:12px;">
        <div class="adm-info-message<?= $messageType === 'error' ? ' adm-info-message-red' : ''; ?>">
            <?= htmlspecialcharsbx($message); ?>
        </div>
    </div>
<?php endif; ?>

<div class="adm-detail-content-wrap" style="margin-top:20px;">
    <div class="adm-detail-title"><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_DIAG_TITLE')); ?></div>
    <div class="adm-detail-content">
        <table class="adm-detail-content-table edit-table">
            <tr>
                <td width="40%"><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_DIR')); ?></td>
                <td><code><?= htmlspecialcharsbx($backupInspect['backupDir']); ?></code></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_COLLECTOR')); ?></td>
                <td><code><?= htmlspecialcharsbx($backupInspect['collector']); ?></code></td>
            </tr>
            <tr>
                <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_SCANNED')); ?></td>
                <td><?= (int) $backupInspect['scannedFiles']; ?></td>
            </tr>
            <?php if (!$backupInspect['dirExists']): ?>
                <tr>
                    <td colspan="2">
                        <span style="color:#c00;"><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_DIR_MISSING')); ?></span>
                    </td>
                </tr>
            <?php elseif ($backupInspect['selected'] === null): ?>
                <tr>
                    <td colspan="2">
                        <span style="color:#c00;"><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_NOT_FOUND')); ?></span>
                    </td>
                </tr>
            <?php else: ?>
                <?php $selected = $backupInspect['selected']; ?>
                <tr class="adm-detail-content-table-accent">
                    <td><strong><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_SELECTED')); ?></strong></td>
                    <td>
                        <strong><?= htmlspecialcharsbx($selected['baseName']); ?></strong><br>
                        <?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_DATE')); ?>:
                        <?= htmlspecialcharsbx($selected['dateLocal']); ?><br>
                        <?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_AGE')); ?>:
                        <strong><?= htmlspecialcharsbx((string) $selected['ageHours']); ?></strong>
                        <?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_AGE_UNIT')); ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if (is_array($backupInspect['metric'] ?? null)): ?>
                <?php $metric = $backupInspect['metric']; ?>
                <tr>
                    <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_METRIC_SENT')); ?></td>
                    <td>
                        <code>backup.age_hours = <?= htmlspecialcharsbx((string) ($metric['value'] ?? '—')); ?></code>
                        <?php if (is_array($metric['tags'] ?? null)): ?>
                            <br><span style="color:#666;">tags: <?= htmlspecialcharsbx(json_encode($metric['tags'], JSON_UNESCAPED_UNICODE)); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </table>

        <?php if (($backupInspect['groups'] ?? []) !== []): ?>
            <p style="margin:12px 0 8px;font-weight:600;"><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_GROUPS')); ?></p>
            <table class="adm-list-table" style="width:100%;">
                <thead>
                    <tr class="adm-list-table-header">
                        <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_GROUP_NAME')); ?></td>
                        <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_GROUP_DATE')); ?></td>
                        <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_GROUP_AGE')); ?></td>
                        <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_GROUP_PARTS')); ?></td>
                        <td><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_GROUP_FILES')); ?></td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backupInspect['groups'] as $group): ?>
                        <?php
                        $isSelected = ($backupInspect['selected']['baseName'] ?? '') === $group['baseName'];
                        $partsPreview = implode(', ', array_slice($group['parts'], 0, 8));
                        if (count($group['parts']) > 8) {
                            $partsPreview .= ' … (+' . (count($group['parts']) - 8) . ')';
                        }
                        ?>
                        <tr<?= $isSelected ? ' style="background:#e8f5e9;"' : ''; ?>>
                            <td><?= $isSelected ? '★ ' : ''; ?><code><?= htmlspecialcharsbx($group['baseName']); ?></code></td>
                            <td><?= htmlspecialcharsbx($group['dateLocal']); ?></td>
                            <td><?= htmlspecialcharsbx((string) $group['ageHours']); ?> ч</td>
                            <td><?= (int) $group['partsCount']; ?></td>
                            <td style="font-size:11px;max-width:420px;word-break:break-all;"><?= htmlspecialcharsbx($partsPreview); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:8px;color:#666;font-size:12px;"><?= htmlspecialcharsbx(Loc::getMessage('VENDOR_MONITORING_BACKUP_GROUPS_HINT')); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';

<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Service\Alert\DiskEvidenceHelper;

final class IncidentNotificationFormatter
{
    public static function checkTypeLabel(string $checkType): string
    {
        return match ($checkType) {
            'heartbeat_missing' => 'Связь с модулем Bitrix',
            'uptime_http' => 'Uptime HTTP',
            'ssl_expiry' => 'SSL сертификат',
            'domain_expiry' => 'Срок домена',
            'disk_low' => 'Мало места на диске',
            'backup_stale' => 'Устаревший бэкап',
            'agents_lag' => 'Просроченные agents Bitrix',
            'modules_updates' => 'Обновления модулей',
            'bitrix_license_expiry' => 'Лицензия 1С-Битрикс',
            default => $checkType,
        };
    }

    public static function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Критично',
            'warning' => 'Предупреждение',
            'info' => 'Инфо',
            default => $severity,
        };
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%d сек', $seconds);
        }

        if ($seconds < 3600) {
            return sprintf('%d мин', (int) round($seconds / 60));
        }

        if ($seconds < 86400) {
            return sprintf('%.1f ч', $seconds / 3600);
        }

        return sprintf('%.1f дн', $seconds / 86400);
    }

    /** @param array<string, mixed> $evidence */
    public static function formatAgentsIncidentTitle(int $overdueCount, int $maxLagSeconds, array $evidence): string
    {
        $stuckAgents = self::stuckAgentsList($evidence);
        $lagText = self::formatDuration($maxLagSeconds);

        if ($overdueCount > 0 && $stuckAgents !== []) {
            $first = $stuckAgents[0];
            $module = is_string($first['module'] ?? null) ? $first['module'] : 'unknown';
            $function = is_string($first['function'] ?? null) ? self::shortenAgentFunction($function) : 'unknown';

            if ($overdueCount === 1) {
                return sprintf('Agents Bitrix: не выполняется «%s» (%s), просрочка %s', $module, $function, $lagText);
            }

            return sprintf(
                'Agents Bitrix: %d просрочено (хуже всего %s — %s, %s)',
                $overdueCount,
                $module,
                $function,
                $lagText,
            );
        }

        return sprintf('Agents Bitrix: задержка выполнения %s', $lagText);
    }

    /** @param array<string, mixed> $evidence */
    public static function formatBitrixLicenseIncidentTitle(int $daysLeft, array $evidence, string $severity): string
    {
        $source = is_string($evidence['source'] ?? null) ? $evidence['source'] : null;
        $suffix = $source === 'support' ? ' (техподдержка)' : '';

        if ($daysLeft === 0) {
            return 'Лицензия 1С-Битрикс истекла'.$suffix;
        }

        if ($severity === 'critical') {
            return sprintf('Лицензия 1С-Битрикс: осталось %d дн.%s', $daysLeft, $suffix);
        }

        return sprintf('Лицензия 1С-Битрикс истекает через %d дн.%s', $daysLeft, $suffix);
    }

    /** @param array<string, mixed> $evidence */
    public static function formatEvidenceDetailLines(string $checkType, array $evidence): array
    {
        return match ($checkType) {
            'agents_lag' => self::formatAgentsLines($evidence),
            'disk_low' => self::formatDiskLines($evidence),
            'backup_stale' => self::formatBackupLines($evidence),
            'modules_updates' => self::formatModulesLines($evidence),
            'uptime_http' => self::formatUptimeLines($evidence),
            'ssl_expiry' => self::formatSslLines($evidence),
            'domain_expiry' => self::formatDomainLines($evidence),
            'heartbeat_missing' => self::formatHeartbeatLines($evidence),
            'bitrix_license_expiry' => self::formatBitrixLicenseLines($evidence),
            default => [],
        };
    }

    /** @param array<string, mixed> $payload */
    public static function formatNotificationBody(array $payload): string
    {
        $checkType = is_string($payload['checkType'] ?? null) ? $payload['checkType'] : 'unknown';
        $evidence = is_array($payload['evidence'] ?? null) ? $payload['evidence'] : [];

        $lines = [];
        if (!empty($payload['isReminder'])) {
            $lines[] = 'Повторное напоминание: проблема ещё не устранена';
            $lines[] = '';
        }

        $lines[] = $payload['title'] ?? 'Уведомление Monitoring Service';
        $lines[] = '';

        if (isset($payload['siteDomain'])) {
            $lines[] = 'Сайт: '.$payload['siteDomain'];
        }

        $lines[] = 'Проверка: '.self::checkTypeLabel($checkType);
        $lines[] = 'Серьёзность: '.self::severityLabel(is_string($payload['severity'] ?? null) ? $payload['severity'] : 'info');

        $detailLines = self::formatEvidenceDetailLines($checkType, $evidence);
        if ($detailLines !== []) {
            $lines[] = '';
            $lines[] = 'Что не так:';
            $lines = [...$lines, ...$detailLines];
        }

        if (isset($payload['openedAt']) && is_string($payload['openedAt']) && $payload['openedAt'] !== '') {
            $lines[] = '';
            $lines[] = 'Открыт: '.self::formatDateTimeRu($payload['openedAt']);
        }

        $lines[] = '';
        $frontendUrl = $payload['frontendUrl'] ?? '';
        if (is_string($frontendUrl) && $frontendUrl !== '') {
            $lines[] = 'Кабинет: '.$frontendUrl.'/incidents';
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $evidence */
    private static function formatAgentsLines(array $evidence): array
    {
        $active = $evidence['activeCount'] ?? null;
        $overdue = $evidence['overdueCount'] ?? null;
        $maxLag = $evidence['maxLagSeconds'] ?? null;

        $lines = [
            'Агенты Bitrix (cron) не отработали к запланированному времени NEXT_EXEC.',
        ];

        if (is_int($active) && is_int($overdue)) {
            $lines[] = sprintf('Активных agents: %d, просрочено: %d.', $active, $overdue);
        }

        if (is_int($maxLag)) {
            $lines[] = 'Максимальная просрочка: '.self::formatDuration($maxLag).'.';
        }

        $lines[] = 'Проверьте: Настройки → Настройки продукта → Агенты; желателен запуск по cron, не только по хитам.';
        $lines[] = '';

        foreach (self::stuckAgentsList($evidence) as $index => $agent) {
            $module = is_string($agent['module'] ?? null) ? $agent['module'] : 'unknown';
            $function = is_string($agent['function'] ?? null) ? $agent['function'] : 'unknown';
            $lagSeconds = is_numeric($agent['lagSeconds'] ?? null) ? (int) $agent['lagSeconds'] : 0;
            $lines[] = sprintf(
                '%d) [%s] %s — просрочка %s',
                $index + 1,
                $module,
                $function,
                self::formatDuration($lagSeconds),
            );
        }

        return $lines;
    }

    /** @param array<string, mixed> $evidence */
    private static function formatDiskLines(array $evidence): array
    {
        $lines = ['На сервере мало свободного места на диске (document root).'];
        $freePercent = $evidence['freePercent'] ?? null;
        if (is_float($freePercent) || is_int($freePercent)) {
            $lines[] = sprintf('Свободно: %.1f%%.', (float) $freePercent);
        }

        $path = $evidence['path'] ?? null;
        if (is_string($path) && $path !== '') {
            $lines[] = 'Путь: '.$path;
        }

        $totalBytes = $evidence['totalBytes'] ?? null;
        $freeBytes = $evidence['freeBytes'] ?? null;
        $usedBytes = $evidence['usedBytes'] ?? null;
        if (is_int($totalBytes) && is_int($freeBytes) && is_int($usedBytes)) {
            $lines[] = sprintf(
                'Объём: свободно %s, занято %s, всего %s.',
                DiskEvidenceHelper::formatBytes($freeBytes),
                DiskEvidenceHelper::formatBytes($usedBytes),
                DiskEvidenceHelper::formatBytes($totalBytes),
            );
        }

        return $lines;
    }

    /** @param array<string, mixed> $evidence */
    private static function formatBackupLines(array $evidence): array
    {
        $status = $evidence['backupStatus'] ?? 'unknown';
        if ($status === 'missing') {
            return [
                'Не найден свежий бэкап Bitrix в /bitrix/backup/.',
                'Рекомендуется настроить регулярное резервное копирование.',
            ];
        }

        $ageHours = $evidence['ageHours'] ?? null;
        $lines = ['Резервная копия Bitrix устарела.'];
        if (is_numeric($ageHours)) {
            $lines[] = 'Последний бэкап '.self::formatAgeHours((float) $ageHours, true, true).'.';
        }

        $lastBackupAt = $evidence['lastBackupAt'] ?? null;
        if (is_string($lastBackupAt) && $lastBackupAt !== '') {
            $lines[] = 'Дата последнего архива: '.self::formatDateTimeRu($lastBackupAt);
        }

        $lastBackupName = $evidence['lastBackupName'] ?? null;
        if (is_string($lastBackupName) && $lastBackupName !== '') {
            $lines[] = 'Архив: '.$lastBackupName;
        }

        return $lines;
    }

    /** @param array<string, mixed> $evidence */
    private static function formatModulesLines(array $evidence): array
    {
        $count = $evidence['updatesAvailableCount'] ?? null;

        return [
            'Для установленных модулей Bitrix доступны обновления.',
            is_int($count) ? sprintf('Количество обновлений: %d.', $count) : 'Проверьте Маркетплейс / обновления в админке.',
        ];
    }

    /** @param array<string, mixed> $evidence */
    private static function formatUptimeLines(array $evidence): array
    {
        $lines = ['Сайт не отвечает на HTTP-проверку (главная страница или URL из настроек).'];
        $url = $evidence['url'] ?? null;
        if (is_string($url) && $url !== '') {
            $lines[] = 'URL: '.$url;
        }

        $httpStatus = $evidence['httpStatus'] ?? null;
        if (is_int($httpStatus) && $httpStatus > 0) {
            $lines[] = sprintf('HTTP-код: %d.', $httpStatus);
        }

        $error = $evidence['error'] ?? null;
        if (is_string($error) && $error !== '') {
            $lines[] = 'Ошибка: '.$error;
        }

        $responseTimeMs = $evidence['responseTimeMs'] ?? null;
        if (is_int($responseTimeMs)) {
            $lines[] = sprintf('Время ответа: %d мс.', $responseTimeMs);
        }

        return $lines;
    }

    /** @param array<string, mixed> $evidence */
    private static function formatSslLines(array $evidence): array
    {
        if (isset($evidence['error']) && is_string($evidence['error'])) {
            return [
                'Не удалось проверить SSL (ошибка handshake).',
                'Хост: '.(is_string($evidence['host'] ?? null) ? $evidence['host'] : '—'),
                'Ошибка: '.$evidence['error'],
            ];
        }

        $daysLeft = $evidence['daysLeft'] ?? null;
        $validTo = $evidence['validTo'] ?? null;
        $lines = ['SSL-сертификат скоро истечёт или уже недействителен.'];
        if (is_int($daysLeft)) {
            $lines[] = sprintf('Осталось дней: %d.', $daysLeft);
        }
        if (is_string($validTo) && $validTo !== '') {
            $lines[] = 'Действует до: '.self::formatDateTimeRu($validTo);
        }

        return $lines;
    }

    /** @param array<string, mixed> $evidence */
    private static function formatDomainLines(array $evidence): array
    {
        $domain = $evidence['domain'] ?? '—';
        $daysLeft = $evidence['daysLeft'] ?? null;
        $expiresAt = $evidence['expiresAt'] ?? null;

        $lines = [sprintf('Домен %s скоро истекает.', $domain)];
        if (is_int($daysLeft)) {
            $lines[] = sprintf('Осталось дней: %d.', $daysLeft);
        }
        if (is_string($expiresAt) && $expiresAt !== '') {
            $lines[] = 'Истекает: '.self::formatDateTimeRu($expiresAt);
        }

        return $lines;
    }

    /** @param array<string, mixed> $evidence */
    private static function formatBitrixLicenseLines(array $evidence): array
    {
        $daysLeft = $evidence['daysLeft'] ?? null;
        $source = is_string($evidence['source'] ?? null) ? $evidence['source'] : null;
        $edition = is_string($evidence['edition'] ?? null) ? $evidence['edition'] : null;

        $lines = ['Срок действия лицензии или техподдержки 1С-Битрикс подходит к концу.'];
        if ($edition !== null && $edition !== '') {
            $lines[] = 'Редакция: '.$edition;
        }
        if (is_int($daysLeft)) {
            $lines[] = sprintf('До ближайшего срока: %d дн.', $daysLeft);
        }
        if ($source === 'support') {
            $lines[] = 'Ближайший срок — окончание техподдержки (обновления).';
        } elseif ($source === 'product') {
            $lines[] = 'Ближайший срок — окончание лицензии продукта.';
        }
        if (is_string($evidence['productExpireDate'] ?? null) && $evidence['productExpireDate'] !== '') {
            $lines[] = 'Лицензия до: '.self::formatDateTimeRu((string) $evidence['productExpireDate']);
        }
        if (is_string($evidence['supportExpireDate'] ?? null) && $evidence['supportExpireDate'] !== '') {
            $lines[] = 'Техподдержка до: '.self::formatDateTimeRu((string) $evidence['supportExpireDate']);
        }
        if (is_numeric($evidence['productDaysLeft'] ?? null) && is_numeric($evidence['supportDaysLeft'] ?? null)) {
            $lines[] = sprintf(
                'Отдельно: лицензия %d дн., техподдержка %d дн.',
                (int) $evidence['productDaysLeft'],
                (int) $evidence['supportDaysLeft'],
            );
        }

        return $lines;
    }

    /** @param array<string, mixed> $evidence */
    private static function formatHeartbeatLines(array $evidence): array
    {
        $seconds = $evidence['secondsSinceLastHeartbeat'] ?? null;
        $last = $evidence['lastHeartbeatAt'] ?? null;

        $lines = ['Модуль на сайте не отправлял сигнал связи с сервером мониторинга.'];
        if (is_int($seconds)) {
            $lines[] = 'Нет связи: '.self::formatDuration($seconds).'.';
        }
        if (is_string($last) && $last !== '') {
            $lines[] = 'Последний сигнал: '.self::formatDateTimeRu($last);
        } else {
            $lines[] = 'Связь с модулем ещё не была установлена.';
        }

        $lines[] = 'Проверьте: модуль установлен, API URL/secret, cron агента модуля.';

        return $lines;
    }

    /** @param array<string, mixed> $evidence
     * @return list<array<string, mixed>>
     */
    private static function stuckAgentsList(array $evidence): array
    {
        $stuckAgents = $evidence['stuckAgents'] ?? [];
        if (!is_array($stuckAgents)) {
            return [];
        }

        return array_values(array_filter($stuckAgents, static fn ($item) => is_array($item)));
    }

    private static function shortenAgentFunction(string $function): string
    {
        $function = trim($function);
        if (strlen($function) <= 60) {
            return $function;
        }

        return substr($function, 0, 57).'...';
    }

    /** Возраст в часах: до 24 ч — в часах, от 24 ч — в днях. */
    public static function formatAgeHours(float $hours, bool $withAgoSuffix = true, bool $withApproximately = false): string
    {
        $prefix = $withApproximately ? 'примерно ' : '';
        $suffix = $withAgoSuffix ? ' назад' : '';

        if ($hours < 1) {
            $minutes = max(1, (int) round($hours * 60));

            return $prefix.$minutes.' '.self::pluralizeRu($minutes, 'минуту', 'минуты', 'минут').$suffix;
        }

        if ($hours < 24) {
            $roundedHours = max(1, (int) round($hours));

            return $prefix.$roundedHours.' '.self::pluralizeRu($roundedHours, 'час', 'часа', 'часов').$suffix;
        }

        $days = max(1, (int) round($hours / 24));

        return $prefix.$days.' '.self::pluralizeRu($days, 'день', 'дня', 'дней').$suffix;
    }

    public static function formatDateTimeRu(\DateTimeInterface|string $value): string
    {
        try {
            $dateTime = $value instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($value)
                : new \DateTimeImmutable($value);
        } catch (\Exception) {
            return is_string($value) ? $value : '';
        }

        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];

        $month = (int) $dateTime->format('n');

        return sprintf(
            '%d %s %d в %d:%02d',
            (int) $dateTime->format('j'),
            $months[$month] ?? $dateTime->format('m'),
            (int) $dateTime->format('Y'),
            (int) $dateTime->format('G'),
            (int) $dateTime->format('i'),
        );
    }

    private static function pluralizeRu(int $count, string $one, string $few, string $many): string
    {
        $mod100 = $count % 100;
        $mod10 = $count % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        if ($mod10 === 1) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }

        return $many;
    }
}

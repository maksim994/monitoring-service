# Changelog

## Unreleased

### Fixed

- Админка: `Loader::includeModule()` до `AdminLang` (исправлен Class not found).
- Админка настроек: явная загрузка lang (ru/en), обёртка `adm-workarea`, stub в `/bitrix/admin/` с абсолютным путём при установке.

### Added

- `MetricsPublisher`: сбор и отправка всех метрик; кнопка «Собрать и отправить все метрики» в настройках модуля.
- API `POST /api/v1/sites/{id}/refresh` и кнопка «Обновить данные» в кабинете (внешние проверки сразу).

### Fixed

- `LicenseCollector`: учёт `Bitrix\Main\Type\Date` (не только `DateTime`) в `getExpireDate` / `getSupportExpireDate`.
- Probes: SSL — `openssl_x509_parse` для PHP 8+ и повтор без verify_peer; домен `.ru` — WHOIS fallback (`whois.tcinet.ru`).
- `AgentsCollector` v3: grace по `AGENT_INTERVAL`, исключение `vendor.monitoring` из алерта, id в `stuckAgents`, диагностика в админке.
- `BackupCollector`: учёт архивов `.enc` / `.enc.gz` (шифрованные копии Bitrix), обход подкаталогов `/bitrix/backup/`, тег `collector=backup_v2`.

### Added

- В настройках модуля: блок «Диагностика резервных копий» и кнопка отправки метрики бэкапа в облако.

### Added

- `LicenseCollector`: метрика `license.days_left` через API `\Bitrix\Main\License` (продукт и техподдержка).
- `BackupCollector`: метрика `backup.age_hours` по каталогу `/bitrix/backup/`.
- `BackupCollector`: учёт многотомных архивов Bitrix (`.tar.gz.1` … `.tar.gz.N`), не только `.tar.gz`.
- `AgentsCollector`: `agents.active_count`, `agents.overdue_count`, `agents.max_lag_seconds`.
- `ModulesCollector`: `modules.installed_count`, `modules.updates_available_count` (кеш 12 ч).
- `DiskCollector`: в метрику диска добавлены `totalBytes`, `freeBytes`, `usedBytes`.

## 0.1.0 — 2026-06-02

### Added

- Подключение к Monitoring Service SaaS через Site ID и API secret.
- Отправка signed heartbeat и metrics batch с HMAC-SHA256.
- Локальная очередь с retry при недоступности API.
- Collectors: environment и disk.
- Агент cron для периодической отправки данных.
- Страница настроек в админке Bitrix.
- Тестовая отправка heartbeat из админки.

### Security

- Проверка `bitrix_sessid` на форме настроек.
- Доступ только для администраторов Bitrix.
- Экранирование вывода через `htmlspecialcharsbx`.
- API secret маскируется в форме; новое значение сохраняется только при явном вводе.

### Known limitations

- Тяжёлая проверка обновлений через `CUpdateClient` не чаще 1 раза в 12 часов.
- Шифрование secret в Option пока не включено (хранится в module options).
- Поддерживается PHP 8.2+ и Bitrix Main module.

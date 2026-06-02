# Bitrix Module Design: модуль мониторинга

## 1. Назначение модуля

Модуль устанавливается на сайт 1С-Битрикс или коробочный портал Битрикс24 и выполняет внутреннюю диагностику проекта. Он собирает технические метрики, отправляет их в SaaS/self-hosted backend, хранит локальную очередь при сбоях сети и может работать в упрощённом module-only режиме.

Модуль не должен менять ядро `/bitrix/`, пользовательские шаблоны и бизнес-логику сайта.

## 2. Идентификатор и структура

Рабочий идентификатор: `vendor.monitoring`.

Финальный vendor нужно выбрать перед публикацией в Marketplace.

```text
/local/modules/vendor.monitoring/
├── install/
│   ├── index.php
│   ├── version.php
│   └── components/
├── lang/
│   └── ru/
├── lib/
│   ├── Application/
│   │   ├── Collector/
│   │   ├── Dto/
│   │   ├── Service/
│   │   └── UseCase/
│   ├── Cli/
│   │   ├── Agent/
│   │   └── Command/
│   ├── Infrastructure/
│   │   ├── Http/
│   │   ├── Notification/
│   │   ├── Storage/
│   │   └── System/
│   ├── Internals/
│   ├── Model/
│   ├── Security/
│   └── ValueObject/
├── admin/
├── options.php
├── default_option.php
├── include.php
└── .settings.php
```

Неймспейс: `Vendor\Monitoring`.

## 3. Режимы работы

### SaaS

Основной режим:

- модуль отправляет heartbeat, metrics и events на облачный API;
- backend возвращает remote config;
- уведомления отправляет SaaS;
- внешние uptime/SSL/DNS проверки выполняются probe-нодами SaaS.

### Self-hosted

То же, что SaaS, но API URL указывает на инфраструктуру клиента.

Особенности:

- поддержка кастомного CA/certificate bundle при необходимости;
- отключение обращений к публичному SaaS;
- возможность указать внутренние endpoints.

### Module-only

Локальный режим:

- нет отправки в SaaS;
- модуль собирает внутренние проверки;
- хранит последнюю историю локально;
- отправляет Telegram/email напрямую;
- показывает страницу статуса в админке.

Ограничение: внешняя проверка доступности не считается надёжной, если выполняется с того же сервера.

## 4. Установка

### `install/version.php`

Содержит версию модуля:

```php
<?php
$arModuleVersion = [
    'VERSION' => '1.0.0',
    'VERSION_DATE' => '2026-06-02 00:00:00',
];
```

### `install/index.php`

Установщик:

- наследуется от `CModule`;
- проверяет права администратора;
- проверяет минимальную версию PHP и модуля `main`;
- регистрирует модуль через `ModuleManager::registerModule`;
- создаёт таблицы через ORM;
- регистрирует агенты;
- копирует admin-файлы, если нужны;
- записывает дефолтные опции.

При удалении:

- удаляет агенты модуля;
- удаляет обработчики событий;
- предлагает оставить или удалить локальные таблицы;
- удаляет опции при полном удалении;
- вызывает `ModuleManager::unRegisterModule`.

## 5. Локальные таблицы

### `vendor_monitoring_queue`

Очередь исходящих payload-ов.

Поля:

- `ID`;
- `EVENT_ID`;
- `TYPE`: heartbeat, metrics, events, log;
- `PAYLOAD`;
- `PAYLOAD_HASH`;
- `STATUS`: pending, processing, sent, failed;
- `ATTEMPT_COUNT`;
- `NEXT_ATTEMPT_AT`;
- `LAST_ERROR`;
- `CREATED_AT`;
- `UPDATED_AT`.

### `vendor_monitoring_log`

Локальный технический журнал.

Поля:

- `ID`;
- `LEVEL`;
- `MESSAGE`;
- `CONTEXT`;
- `CREATED_AT`.

### `vendor_monitoring_state`

Состояние collectors.

Поля:

- `ID`;
- `COLLECTOR`;
- `LAST_RUN_AT`;
- `LAST_SUCCESS_AT`;
- `LAST_ERROR`;
- `STATE_JSON`;

### `vendor_monitoring_local_incident`

Только для module-only.

Поля:

- `ID`;
- `TYPE`;
- `SEVERITY`;
- `STATUS`;
- `TITLE`;
- `PAYLOAD`;
- `OPENED_AT`;
- `RESOLVED_AT`.

## 6. Опции модуля

Опции через `Bitrix\Main\Config\Option`:

- `mode`: saas, self_hosted, module_only;
- `api_url`;
- `site_id`;
- `api_secret_encrypted`;
- `config_version`;
- `collector_interval`;
- `enabled_collectors`;
- `queue_ttl_days`;
- `queue_max_items`;
- `debug_log_enabled`;
- `telegram_bot_token_encrypted`;
- `telegram_chat_id`;
- `email_to`;
- `last_successful_send_at`;
- `last_remote_config_at`.

Секреты хранить в зашифрованном виде, не выводить полностью в интерфейсе и не писать в лог.

## 7. Административный интерфейс

Разделы:

- Подключение.
- Проверки.
- Очередь.
- Локальный статус.
- Уведомления module-only.
- Диагностика.

### Подключение

Поля:

- режим работы;
- API URL;
- Site ID;
- API Secret;
- кнопка «Проверить подключение»;
- кнопка «Отправить тестовый heartbeat»;
- статус последней отправки;
- версия remote config.

### Проверки

Пользователь может включать и отключать collectors:

- environment;
- disk;
- backup;
- modules;
- agents;
- database;
- cache;
- mail;
- logs.

### Очередь

Показывает:

- количество pending;
- количество failed;
- последнюю ошибку;
- кнопку повторной отправки failed;
- кнопку очистки старых sent/failed записей.

### Диагностика

Показывает:

- версию модуля;
- версию Bitrix;
- версию PHP;
- доступность API URL;
- права на запись в таблицы модуля;
- статус агента;
- рекомендацию перевести агенты на cron.

## 8. Агенты и cron

Основной агент:

```php
\Vendor\Monitoring\Cli\Agent\CollectorAgent::run();
```

Интервал MVP: 300 секунд.

Задачи агента:

1. Подключить модуль.
2. Получить локальную конфигурацию.
3. Запустить enabled collectors.
4. Сформировать heartbeat и metrics batch.
5. Поставить payload в локальную очередь.
6. Обработать очередь отправки.
7. Обновить локальный state.

Требования:

- агент должен возвращать строку повторного вызова;
- длительные операции ограничивать таймаутом;
- не блокировать пользовательские хиты;
- рекомендовать cron-режим для production;
- не хранить состояние в статических свойствах.

На новых Bitrix-версиях можно дополнительно рассмотреть console command для ручного запуска:

```bash
php bitrix/bitrix.php monitoring:collect
```

## 9. Collectors MVP

### EnvironmentCollector

Собирает:

- версию PHP;
- версию Bitrix main;
- кодировку;
- document root;
- server software;
- memory_limit;
- max_execution_time;
- upload_max_filesize;
- timezone;
- список ключевых PHP-extensions: curl, mbstring, openssl, mysqli, gd, intl, zip.

### DiskCollector

Собирает:

- общий размер диска для document root;
- свободное место;
- процент свободного места;
- размер `/upload/`;
- размер cache-директорий, если доступно и не слишком дорого.

Ограничения:

- не сканировать весь диск глубоко на каждом запуске;
- использовать лимит времени;
- кешировать тяжёлые вычисления;
- передавать только агрегаты, не имена файлов.

### BackupCollector

Проверяет:

- дату последнего стандартного бэкапа Bitrix, если можно определить;
- наличие свежих файлов backup в стандартных директориях;
- возраст последнего бэкапа;
- предупреждение, если бэкап старше порога.

Если API/путь недоступны, collector возвращает состояние `unknown`, а не ошибку модуля.

### ModulesCollector

Собирает:

- список установленных модулей;
- версии модулей;
- версию main;
- доступность обновлений, если безопасно получить без тяжёлой операции;
- дату последней проверки обновлений, если доступна.

Не передавать лицензионные ключи.

### AgentsCollector

Собирает:

- количество активных агентов;
- количество просроченных агентов;
- количество агентов с большим `NEXT_EXEC` lag;
- топ зависших агентов по module/function без чувствительных аргументов;
- признак cron/hit режима, если возможно определить.

### DatabaseCollector

MVP:

- тип подключения;
- размер БД, если доступно;
- количество таблиц;
- ошибка доступа, если прав недостаточно.

Расширение:

- размеры ключевых таблиц;
- рост таблиц;
- долгие запросы при наличии источника данных.

### CacheCollector

Расширение после MVP:

- статус composite;
- размеры cache-директорий;
- managed cache metrics;
- рекомендации по очистке.

### MailCollector

Расширение после MVP:

- количество неотправленных событий в почтовой очереди;
- дата последней успешной отправки;
- ошибки, если доступны.

### LogsCollector

Расширение после MVP:

- количество ошибок по allowlist логов;
- новые critical/error записи;
- не передавать полные строки, если они могут содержать персональные данные;
- поддержать маскирование.

## 10. Payload model

### Heartbeat

```json
{
  "eventId": "uuid",
  "collectedAt": "2026-06-02T11:00:00Z",
  "module": {
    "version": "1.0.0",
    "mode": "saas"
  },
  "environment": {
    "bitrixVersion": "25.0.0",
    "phpVersion": "8.2.12"
  }
}
```

### Metric

```json
{
  "key": "disk.free_percent",
  "value": 24.5,
  "unit": "percent",
  "tags": {
    "path": "document_root"
  }
}
```

### Event

```json
{
  "eventId": "uuid",
  "type": "backup.last_success",
  "occurredAt": "2026-06-02T11:00:00Z",
  "payload": {
    "lastBackupAt": "2026-06-01T03:00:00Z",
    "ageHours": 32
  }
}
```

## 11. HTTP sender

Использовать `Bitrix\Main\Web\HttpClient`.

Настройки:

- `socketTimeout`: 5 секунд;
- `streamTimeout`: 10 секунд;
- `redirect`: false;
- `disableSslVerification`: false;
- user agent `VendorMonitoringModule/1.0.0`.

Headers:

- `Content-Type: application/json`;
- `X-Site-Id`;
- `X-Timestamp`;
- `X-Signature`;
- `X-Module-Version`;
- `X-Request-Id`.

Подпись:

```text
hex_hmac_sha256(api_secret, timestamp + "." + raw_body)
```

Требования:

- timestamp в UTC;
- request id генерировать на каждую отправку;
- при 2xx помечать запись queue как sent;
- при 4xx не ретраить бесконечно, кроме 408/429;
- при 5xx и network errors использовать backoff;
- не логировать raw body целиком в debug по умолчанию.

## 12. Локальная очередь

Алгоритм:

1. Collector формирует payload.
2. Payload сохраняется в `vendor_monitoring_queue`.
3. QueueProcessor берёт пачку pending по `NEXT_ATTEMPT_AT`.
4. Статус меняется на processing.
5. Sender отправляет payload.
6. При успехе статус sent.
7. При ошибке attempt + 1, backoff, status pending/failed.

Backoff:

- 1 минута;
- 5 минут;
- 15 минут;
- 1 час;
- далее каждые 6 часов до TTL.

Ограничения:

- max items по опции;
- TTL для sent и failed;
- при переполнении удалять самые старые non-critical записи;
- heartbeat должен иметь приоритет.

## 13. Remote config

Модуль периодически запрашивает конфигурацию:

- включенные collectors;
- интервалы;
- лимиты;
- sampling;
- максимальный размер payload;
- флаги функций.

Если remote config недоступен:

- использовать последнюю успешную конфигурацию;
- если её нет, использовать default_option;
- не останавливать сбор полностью.

## 14. Module-only режим

В module-only режиме:

- collectors работают локально;
- правила проверяются внутри модуля;
- локальные инциденты пишутся в таблицу;
- Telegram/email отправляются напрямую;
- админка показывает статус.

Минимальные локальные правила:

- диск меньше 15% warning, меньше 5% critical;
- бэкап старше 3 дней warning, старше 7 дней critical;
- SSL менее 14 дней warning, менее 3 дней critical, если проверка доступна;
- heartbeat не применяется, так как backend отсутствует;
- агенты просрочены более чем на 30 минут warning.

## 15. Безопасность

Требования:

- Все формы админки защищены `bitrix_sessid_post()` и `check_bitrix_sessid()`.
- Доступ к настройкам только администраторам или пользователям с правом модуля.
- Все значения в HTML выводить через `htmlspecialcharsbx`.
- JSON кодировать через `Bitrix\Main\Web\Json`.
- Секреты шифровать.
- Секреты не логировать.
- Не передавать персональные данные по умолчанию.
- Не читать произвольные файлы по пользовательскому пути.
- Для внешних URL применять allowlist схем и проверку на private IP.
- Не выполнять команды shell.
- Не делать тяжёлые сканирования без лимитов.

## 16. Права модуля

Минимальные уровни:

- `D`: доступ запрещён;
- `R`: просмотр статуса;
- `W`: изменение настроек;
- `X`: администрирование подключения и секретов.

Административные действия должны проверять права через `CMain::GetUserRight`.

## 17. Логирование

Локальный лог должен хранить:

- ошибки отправки;
- ошибки collectors;
- ошибки remote config;
- результат теста подключения;
- диагностические события установки.

Не хранить:

- API secret;
- полный Authorization/header;
- персональные данные;
- полные payload-ы с потенциально чувствительными значениями.

## 18. Marketplace требования

Перед публикацией:

- проверить установку на чистом Bitrix;
- проверить удаление;
- подготовить русские lang-файлы;
- подготовить описание;
- подготовить скриншоты админки;
- описать состав передаваемых данных;
- указать совместимые редакции;
- добавить changelog;
- проверить, что модуль не требует правки ядра.

## 19. Тестирование модуля

Unit/integration:

- генерация HMAC;
- backoff очереди;
- collectors на mock-данных;
- сериализация payload;
- remote config fallback.

Ручные стенды:

- свежий Bitrix с PHP 8.2;
- legacy Bitrix на shared-hosting;
- портал Битрикс24 коробка;
- сайт с переполненным cache;
- сайт без доступа к backup директории;
- сайт с агентами на hit-режиме.

## 20. Критерии готовности модуля MVP

Модуль готов, если:

- устанавливается и удаляется стандартными средствами;
- создаёт нужные таблицы;
- регистрирует и удаляет агент;
- имеет страницу настроек;
- проходит handshake;
- отправляет heartbeat;
- собирает MVP collectors;
- сохраняет payload в локальную очередь;
- ретраит отправку при сбое API;
- получает remote config;
- работает в module-only режиме для базовых проверок;
- не передаёт персональные данные;
- не создаёт заметной нагрузки на сайт.

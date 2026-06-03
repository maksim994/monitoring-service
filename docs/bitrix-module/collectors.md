# Bitrix Module Collectors

## Общие Требования

- Collector не должен менять данные сайта.
- Collector должен иметь таймаут выполнения.
- При невозможности получить данные возвращать `unknown`, а не ломать общий сбор.
- Не передавать персональные данные, содержимое заказов, форм, cookie, сессии и файлы.
- Тяжёлые вычисления кешировать и выполнять не чаще заданного интервала.

## EnvironmentCollector

Собирает:

- PHP version;
- Bitrix main version;
- encoding;
- document root aggregate info;
- server software;
- memory_limit;
- max_execution_time;
- upload_max_filesize;
- timezone;
- наличие extensions: curl, mbstring, openssl, mysqli, gd, intl, zip.

Примеры метрик:

- `environment.php_version`
- `environment.bitrix_version`
- `environment.extension.curl_enabled`

## DiskCollector

Собирает:

- общий размер диска для document root;
- свободное место;
- процент свободного места;
- агрегированный размер `/upload/`, если доступно безопасно;
- агрегированный размер cache-директорий, если доступно безопасно.

Ограничения:

- не сканировать весь диск глубоко на каждом запуске;
- ограничивать глубину и время;
- не передавать имена файлов;
- кешировать тяжёлые результаты.

Примеры метрик:

- `disk.free_percent`
- `disk.free_bytes`
- `disk.upload_size_bytes`

## BackupCollector

Собирает:

- дату последнего стандартного бэкапа Bitrix;
- возраст последнего бэкапа;
- состояние `ok`, `stale`, `missing`, `unknown`.

Ограничения:

- не читать содержимое архивов;
- не передавать имена файлов, если они могут содержать данные клиента;
- при недоступности пути возвращать `unknown`.

Примеры событий:

- `backup.last_success`
- `backup.status_changed`

## ModulesCollector

Собирает:

- список установленных модулей;
- версии модулей;
- версию `main`;
- дату последней проверки обновлений, если доступна;
- наличие доступных обновлений, если проверка безопасна.

Запрещено:

- передавать лицензионные ключи;
- инициировать тяжёлую проверку обновлений на каждом запуске.

Примеры метрик:

- `modules.installed_count`
- `modules.updates_available_count`
- `modules.main_version`

## LicenseCollector

Собирает через `\Bitrix\Main\Application::getInstance()->getLicense()`:

- дней до окончания лицензии продукта (`getExpireDate`);
- дней до окончания техподдержки (`getSupportExpireDate`);
- редакцию (`getName`);
- признаки demo и time-bound.

В метрику `license.days_left` попадает **меньшее** из доступных сроков (что наступит раньше). Статус `unlimited` — без ограничения по сроку, инциденты не создаются.

Запрещено:

- передавать лицензионные ключи (`getKey` и аналоги не вызываются).

## AgentsCollector

Собирает:

- количество активных agents;
- количество просроченных agents;
- максимальный `NEXT_EXEC` lag;
- топ зависших agents по module/function без чувствительных аргументов;
- признак cron/hit режима, если возможно определить.

Примеры метрик:

- `agents.active_count`
- `agents.overdue_count`
- `agents.max_lag_seconds`

## DatabaseCollector

Не обязательный для первой поставки MVP, но допускается как safe collector.

Собирает:

- тип подключения;
- количество таблиц;
- размер БД, если доступно;
- ошибку доступа в sanitized виде.

## Collector Result Contract

Collector возвращает:

```json
{
  "collector": "disk",
  "status": "ok",
  "collectedAt": "2026-06-02T11:00:00Z",
  "metrics": [],
  "events": [],
  "error": null
}
```

Статусы:

- `ok`;
- `partial`;
- `unknown`;
- `failed`.

## Performance Limits

Дефолты MVP:

- общий collector cycle: до 20 секунд;
- один collector: до 5 секунд;
- max payload: 256 KB;
- max metrics per batch: 500;
- max events per batch: 500.

Лимиты могут приходить из remote config.

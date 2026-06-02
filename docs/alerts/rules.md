# Alert Rules

## Назначение

Alert engine превращает metrics, events и probe results в инциденты. Его главные задачи: не создавать шум, не дублировать одну проблему и автоматически закрывать инциденты после восстановления.

## Типы Правил MVP

| Type | Источник | Пример |
| --- | --- | --- |
| threshold | Metric | `disk.free_percent < 15` |
| expiry | Event/metric date | SSL истекает менее чем через 14 дней |
| missing | Absence of data | Нет heartbeat дольше 30 минут |
| probe_failure | Probe result | HTTP check failed на двух probe-нодах |
| state | Metric/event state | Collector вернул forbidden state |

## Fingerprint

Fingerprint рассчитывается детерминированно:

```text
site_id + ":" + check_type + ":" + normalized_problem_key
```

Примеры:

- `site_1:ssl_expiry:example.ru`
- `site_1:disk_low:document_root`
- `site_1:agent_stuck:agent_id_15`

Если активный инцидент с таким fingerprint уже есть, engine добавляет `incident_event` и обновляет `last_evidence_json`.

## Debounce

### Uptime Critical

- Открыть incident только после 2 последовательных ошибок с одной probe-ноды или ошибки с 2 разных probe-нод.
- Закрыть incident только после 2 успешных проверок подряд.

### Heartbeat Missing

- Warning: нет heartbeat больше 15 минут.
- Critical: нет heartbeat больше 30 минут.
- Закрытие: первый успешный heartbeat.

### Регламентные Риски

Для SSL, backup, disk и agents warning можно создавать сразу, если данные свежие и collector уверен в значении. Если collector вернул `unknown`, открывать info/warning только при повторе.

## Default Thresholds

| Check | Warning | Critical |
| --- | --- | --- |
| SSL expiry | `< 14 days` | `< 3 days` или handshake failed |
| Domain expiry | `< 30 days` | `< 7 days` |
| Disk free | `< 15%` | `< 5%` |
| Backup age | `> 3 days` | `> 7 days` |
| Agents lag | `> 30 min` | `> 2 hours` |
| HTTP uptime | Debounced failure | Confirmed multi-probe failure |

## Maintenance Window

Если сайт или check находится в maintenance:

- Новые инциденты не создаются.
- Metrics и probe results сохраняются.
- Уже открытые инциденты не закрываются автоматически, если правило не подтверждено успешными данными.
- При выходе из окна правило оценивается заново.

## Mute

Mute подавляет уведомления и может применяться к:

- incident;
- check;
- site;
- organization.

Muted incident остаётся видимым в кабинете и продолжает получать evidence updates.

## Auto Resolve

Инцидент автоматически закрывается, когда:

- правило больше не нарушается;
- данные достаточно свежие;
- выполнен resolve debounce, если он задан;
- maintenance window не маскирует отсутствие данных.

## Idempotency

- Повторный ingest одного `eventId` не должен создавать новые incident events.
- Worker может повторно обработать сообщение очереди без изменения итогового состояния.
- Partial unique index защищает от дублей активных инцидентов.

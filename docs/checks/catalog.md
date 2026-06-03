# Checks Catalog

## MVP Checks

| Check | Source | Default Interval | Purpose |
| --- | --- | --- | --- |
| `uptime_http` | Probe | 1-15 minutes by plan | Проверка доступности URL |
| `ssl_expiry` | Probe | 12 hours | Срок SSL и handshake |
| `domain_expiry` | Probe | 24 hours | Срок домена best-effort |
| `disk_low` | Module | 5 minutes | Свободное место |
| `backup_stale` | Module | 1 hour | Свежесть резервных копий |
| `modules_updates` | Module | 12 hours | Установленные модули и обновления |
| `agents_lag` | Module | 5 minutes | Просроченные agents |
| `bitrix_license_expiry` | Module | 24 hours | Срок лицензии и техподдержки 1С-Битрикс (API `License`) |
| `heartbeat_missing` | Backend | 1 minute evaluation | Связь модуля с backend |

## Status Values

- `ok`;
- `warning`;
- `critical`;
- `unknown`;
- `disabled`.

## Evidence Requirements

Каждая проверка должна сохранять evidence:

- источник данных;
- collected/probed timestamp;
- значение, нарушившее правило;
- threshold;
- error code/message в sanitized виде.

## Plan Limits

| Plan | Uptime Interval | History |
| --- | --- | --- |
| Free | 15 minutes | 7 days |
| Basic | 5 minutes | 90 days |
| Agency | 1-3 minutes | 180+ days |
| Enterprise | Contract | Contract |

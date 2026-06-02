# Bitrix Collectors Spike

## Цель

Проверить, какие технические данные реально и безопасно собирать на разных Bitrix-окружениях.

## Окружения

- Fresh Bitrix with PHP 8.2.
- Legacy Bitrix.
- Boxed Bitrix24.
- Shared-hosting.
- Large `/upload/`.
- Agents on cron.
- Agents on hit mode.

## Проверяемые Collectors

- EnvironmentCollector.
- DiskCollector.
- BackupCollector.
- ModulesCollector.
- AgentsCollector.
- Optional DatabaseCollector.

## Что Фиксировать

| Field | Meaning |
| --- | --- |
| Environment | Bitrix/PHP version, hosting type |
| Collector | Collector name |
| Result | ok, partial, unknown, failed |
| Duration | Execution time |
| Payload size | JSON payload bytes |
| Errors | Sanitized error |
| Notes | API limitations or unsafe data |

## Success Criteria

- Environment and disk collectors work on all stands.
- Backup and modules collectors return `unknown` instead of fatal errors when unavailable.
- Agents collector detects overdue agents without sensitive arguments.
- No collector exceeds default timeout in normal conditions.
- No personal data is collected.

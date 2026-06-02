# Module Load Limits

## Цель

Bitrix-модуль не должен создавать заметной нагрузки на клиентский сайт.

## Default Limits

| Limit | Value |
| --- | --- |
| Collector cycle | 20 seconds |
| Single collector | 5 seconds |
| Payload size | 256 KB |
| Metrics per batch | 500 |
| Events per batch | 500 |
| Directory scan depth | 3 |
| Queue max items | 10 000 |

## Heavy Operations

Heavy operations must be:

- cached;
- time-limited;
- disabled by remote config when needed;
- run less frequently than heartbeat.

## Recommended Intervals

| Collector | Interval |
| --- | --- |
| environment | 12 hours |
| disk | 5 minutes |
| backup | 1 hour |
| modules | 12 hours |
| agents | 5 minutes |
| database | 12 hours |

## Stop Conditions

Collector should stop and return `partial` or `unknown` if:

- timeout exceeded;
- memory limit near exhaustion;
- directory scan limit reached;
- required Bitrix API unavailable;
- access denied.

## Production Recommendation

Use cron mode for Bitrix agents. Hit-based agents are acceptable for beta testing but should be marked with a recommendation in diagnostics.

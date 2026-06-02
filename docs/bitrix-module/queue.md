# Bitrix Module Local Queue

## Назначение

Локальная очередь защищает данные от потери при временной недоступности SaaS/self-hosted API. Collector cycle сохраняет payload локально, а QueueProcessor отправляет записи с retry/backoff.

## Таблица `vendor_monitoring_queue`

| Field | Meaning |
| --- | --- |
| `ID` | Primary key |
| `EVENT_ID` | UUID payload/event for idempotency |
| `TYPE` | `heartbeat`, `metrics`, `events`, `log` |
| `PAYLOAD` | JSON payload |
| `PAYLOAD_HASH` | Hash for deduplication/debug |
| `STATUS` | `pending`, `processing`, `sent`, `failed` |
| `ATTEMPT_COUNT` | Number of send attempts |
| `NEXT_ATTEMPT_AT` | Next retry time |
| `LAST_ERROR` | Sanitized last error |
| `CREATED_AT` | Created timestamp |
| `UPDATED_AT` | Updated timestamp |

## Processing Algorithm

1. Collector cycle creates heartbeat, metrics and events payloads.
2. Payloads are stored as `pending`.
3. QueueProcessor selects records where `NEXT_ATTEMPT_AT <= now`.
4. Record status changes to `processing`.
5. Sender sends payload to API.
6. On 2xx: status becomes `sent`.
7. On retryable error: `ATTEMPT_COUNT` increments, status returns to `pending`, `NEXT_ATTEMPT_AT` moves forward.
8. On non-retryable error: status becomes `failed`.

## Backoff

| Attempt | Delay |
| --- | --- |
| 1 | 1 minute |
| 2 | 5 minutes |
| 3 | 15 minutes |
| 4 | 1 hour |
| 5+ | 6 hours until TTL |

## Retryable Errors

- Network errors.
- Timeout.
- HTTP 408.
- HTTP 429.
- HTTP 5xx.

## Non-retryable Errors

- HTTP 400 validation error.
- HTTP 401 invalid signature.
- HTTP 403 site disabled or key revoked.
- HTTP 413 payload too large.

## Priorities

- Heartbeat has highest priority.
- Metrics and events are normal priority.
- Debug logs are low priority and may be dropped first.

## Limits

- `queue_max_items` default: 10 000.
- `queue_ttl_days` default: 14.
- Sent records can be cleaned earlier.
- If queue is full, delete oldest `sent`, then oldest low-priority `failed`, then reject new low-priority logs.

## Safety

- Do not store API secret in queue payload.
- Do not log raw payload by default.
- Keep `LAST_ERROR` sanitized.
- Queue processing must be lock-protected to avoid duplicate sends.

# Health Checks

## Назначение

Health endpoints нужны для Docker Compose, self-hosted preview, uptime monitoring SaaS и внутренней диагностики.

## Endpoints

### API

```text
GET /health/live
GET /health/ready
```

`/health/live` проверяет, что процесс отвечает.

`/health/ready` проверяет:

- PostgreSQL connection;
- Redis connection;
- TimescaleDB connection;
- migration state;
- queue basic availability.

### Worker

Worker health can be exposed through API metrics or a heartbeat table/cache key:

```text
worker:last_seen:{worker_name}
```

Stale worker threshold MVP: 2 minutes.

### Scheduler

Scheduler writes:

```text
scheduler:last_tick
```

Stale scheduler threshold MVP: 2 minutes.

### Probe

```text
GET /health/live
GET /health/ready
```

Readiness checks:

- can resolve DNS;
- can reach ingest API;
- local clock is within allowed drift;
- outbound HTTP client initialized.

## Response Format

```json
{
  "status": "ok",
  "service": "api",
  "time": "2026-06-02T11:00:00Z",
  "checks": {
    "postgres": "ok",
    "redis": "ok",
    "timescaledb": "ok"
  }
}
```

Status values:

- `ok`;
- `degraded`;
- `failed`.

## Internal Alerts

Create internal alerts for:

- API readiness failed;
- queue size above threshold;
- worker stale;
- scheduler stale;
- probe node stale;
- notification error rate above threshold;
- DB latency above threshold.

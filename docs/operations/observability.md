# Observability

## Цель

Сервис мониторинга сам должен быть наблюдаемым. Нельзя допустить ситуацию, когда клиентский сайт не мониторится из-за незаметной ошибки ingest, очередей или notification worker.

## Metrics

### Ingest

- `ingest_requests_total`.
- `ingest_rejected_signatures_total`.
- `ingest_latency_seconds`.
- `ingest_payload_size_bytes`.
- `ingest_queue_size`.

### Workers

- `worker_jobs_processed_total`.
- `worker_jobs_failed_total`.
- `worker_job_duration_seconds`.
- `worker_oldest_pending_age_seconds`.

### Alerts And Incidents

- `incidents_opened_total`.
- `incidents_resolved_total`.
- `incidents_active_count`.
- `alert_rule_evaluations_total`.
- `alert_rule_errors_total`.

### Notifications

- `notification_delivery_total`.
- `notification_delivery_failed_total`.
- `notification_delivery_latency_seconds`.
- `notification_retry_count`.

### Probes

- `probe_checks_total`.
- `probe_check_duration_seconds`.
- `probe_failures_total`.
- `probe_node_last_seen`.

### Database

- `db_query_duration_seconds`.
- `db_connections_active`.
- `timeseries_insert_total`.

## Logs

All services write structured JSON logs with:

- timestamp;
- level;
- service;
- request id;
- correlation id;
- organization id where safe;
- site id where safe;
- message;
- sanitized context.

Do not log:

- secrets;
- raw request bodies by default;
- personal data;
- webhook tokens;
- bot tokens.

## Tracing

MVP minimum:

- propagate `X-Request-Id`;
- include request id in queue messages;
- include request id in logs and delivery attempts.

OpenTelemetry can be added post-MVP.

## Dashboards

Minimum Grafana dashboards:

- SaaS overview;
- ingest health;
- queue health;
- notification delivery;
- probe health;
- database latency.

## Internal Alert Thresholds

| Alert | Threshold |
| --- | --- |
| Ingest API down | 2 failed checks |
| Queue oldest pending | > 5 minutes |
| Worker stale | > 2 minutes |
| Notification failure rate | > 10% over 10 minutes |
| Probe node stale | > 3 minutes |
| DB latency P95 | > 500 ms |

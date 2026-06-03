# Database Schema

## Назначение

База данных хранит бизнес-сущности SaaS, настройки мониторинга, инциденты, уведомления и time-series данные. MVP использует PostgreSQL 16 и TimescaleDB extension.

## Принципы

- Все пользовательские данные имеют `organization_id`.
- Любой запрос кабинета обязан фильтроваться по текущей организации.
- Фоновые задачи обязаны передавать `organization_id` и `site_id` явно.
- Time-series таблицы партиционируются по времени.
- Секреты не хранятся в открытом виде.
- JSON поля допустимы для гибких settings/evidence, но основные фильтры должны быть колонками.

## Business Tables

### organizations

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk | Organization id |
| name | varchar(255) | Display name |
| plan_code | varchar(50) | free, basic, agency, enterprise |
| status | varchar(30) | active, suspended, deleted |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

### users

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk | User id |
| email | citext unique | Login email |
| password_hash | varchar(255) | Password hash |
| name | varchar(255) | Display name |
| status | varchar(30) | active, invited, disabled |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

### organization_users

| Column | Type | Notes |
| --- | --- | --- |
| organization_id | uuid fk |  |
| user_id | uuid fk |  |
| role | varchar(30) | owner, admin, integrator, operator, viewer |
| created_at | timestamptz |  |

Primary key: `(organization_id, user_id)`.

### projects

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| organization_id | uuid fk | Tenant scope |
| client_name | varchar(255) nullable | Client name for agencies |
| name | varchar(255) | Project name |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

### sites

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk | Internal site id |
| organization_id | uuid fk | Tenant scope |
| project_id | uuid fk nullable |  |
| domain | varchar(255) | Normalized domain |
| site_url | text | Canonical URL |
| status | varchar(30) | pending, ok, warning, critical, disabled |
| module_version | varchar(50) nullable | Last known module version |
| bitrix_version | varchar(50) nullable | Last known Bitrix version |
| php_version | varchar(50) nullable | Last known PHP version |
| last_heartbeat_at | timestamptz nullable |  |
| config_version | integer | Remote config version |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

Recommended indexes:

- `(organization_id, status)`.
- `(organization_id, domain)`.
- `(organization_id, last_heartbeat_at)`.

### site_keys

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| site_id | uuid fk |  |
| key_id | varchar(64) unique | Public key identifier if needed |
| secret_hash | varchar(255) | Hash or encrypted verifier |
| active_from | timestamptz |  |
| active_to | timestamptz nullable |  |
| revoked_at | timestamptz nullable |  |
| created_at | timestamptz |  |

Full secret is shown only once on creation.

### checks

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| organization_id | uuid fk | Denormalized tenant scope |
| site_id | uuid fk |  |
| type | varchar(80) | uptime_http, ssl_expiry, domain_expiry, disk_low, backup_stale, agents_lag, modules_updates, bitrix_license_expiry, heartbeat_missing |
| enabled | boolean |  |
| interval_seconds | integer |  |
| settings_json | jsonb | URL, method, thresholds |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

Indexes:

- `(organization_id, site_id, type)`.
- `(enabled, interval_seconds)` for scheduler selection.

### alert_rules

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| organization_id | uuid fk | Tenant scope |
| site_id | uuid fk nullable | Null means organization template |
| check_type | varchar(80) |  |
| severity | varchar(20) | info, warning, critical |
| condition_json | jsonb | Rule condition |
| debounce_seconds | integer |  |
| repeat_seconds | integer nullable | Notification repeat |
| enabled | boolean |  |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

### maintenance_windows

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| organization_id | uuid fk | Tenant scope |
| site_id | uuid fk |  |
| check_type | varchar(80) nullable | Null = all checks on site |
| title | varchar(255) | Human label |
| starts_at | timestamptz | Window start |
| ends_at | timestamptz | Window end |
| cancelled_at | timestamptz nullable | Early cancel |
| created_by | uuid nullable | User who created |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

### incidents

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| organization_id | uuid fk | Tenant scope |
| site_id | uuid fk |  |
| check_type | varchar(80) |  |
| fingerprint | varchar(255) | Deduplication key |
| severity | varchar(20) | info, warning, critical |
| status | varchar(30) | open, acknowledged, resolved, muted |
| title | varchar(255) |  |
| opened_at | timestamptz |  |
| acknowledged_at | timestamptz nullable |  |
| resolved_at | timestamptz nullable |  |
| muted_until | timestamptz nullable |  |
| last_evidence_json | jsonb | Latest proof |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

Required partial unique index:

```sql
CREATE UNIQUE INDEX incidents_active_unique
ON incidents (site_id, check_type, fingerprint)
WHERE status IN ('open', 'acknowledged');
```

### incident_events

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| incident_id | uuid fk |  |
| type | varchar(80) | opened, evidence_updated, acknowledged, resolved, notification_sent |
| message | text | Human-readable event |
| payload_json | jsonb | Structured event |
| created_by | uuid nullable | User id or null for system |
| created_at | timestamptz |  |

### notification_channels

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| organization_id | uuid fk | Tenant scope |
| type | varchar(30) | telegram, email, webhook |
| name | varchar(255) |  |
| settings_encrypted | text | Encrypted settings |
| enabled | boolean |  |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

### notification_deliveries

| Column | Type | Notes |
| --- | --- | --- |
| id | uuid pk |  |
| organization_id | uuid fk | Tenant scope |
| incident_id | uuid fk |  |
| channel_id | uuid fk |  |
| status | varchar(30) | pending, sent, failed, skipped |
| attempt | integer |  |
| error | text nullable | Sanitized error |
| sent_at | timestamptz nullable |  |
| next_attempt_at | timestamptz nullable |  |
| created_at | timestamptz |  |
| updated_at | timestamptz |  |

## Time-series Tables

### metrics

Timescale hypertable partitioned by `time`.

| Column | Type | Notes |
| --- | --- | --- |
| time | timestamptz | Collected time |
| organization_id | uuid | Tenant scope |
| site_id | uuid |  |
| key | varchar(120) | Metric key |
| value_float | double precision nullable | Numeric value |
| value_string | text nullable | String value |
| value_bool | boolean nullable | Boolean value |
| unit | varchar(30) nullable |  |
| tags_json | jsonb | Low-cardinality tags |
| event_id | uuid nullable | Idempotency |

Indexes:

- `(site_id, key, time DESC)`.
- `(organization_id, time DESC)`.
- Unique best-effort index on `(site_id, event_id, key)` when `event_id IS NOT NULL`.

### probe_results

Timescale hypertable partitioned by `time`.

| Column | Type | Notes |
| --- | --- | --- |
| time | timestamptz | Probe execution time |
| organization_id | uuid | Tenant scope |
| site_id | uuid |  |
| check_id | uuid |  |
| probe_id | varchar(80) | Probe node id |
| status | varchar(30) | ok, failed, timeout, unknown |
| http_code | integer nullable |  |
| response_time_ms | integer nullable |  |
| error_code | varchar(80) nullable |  |
| error_message | text nullable | Sanitized |
| evidence_json | jsonb | Structured proof |

Indexes:

- `(site_id, check_id, time DESC)`.
- `(probe_id, time DESC)`.
- `(organization_id, time DESC)`.

## Tenant Scope Rules

- API Gateway resolves current `organization_id` from user session/JWT.
- All repository methods require explicit tenant context.
- Background messages include `organization_id` and `site_id`.
- Cross-organization access is blocked before business logic.
- Audit log records sensitive actions with actor, organization and request id.

## Retention MVP

| Plan | Business history | Metrics history |
| --- | --- | --- |
| Free | 7 days | 7 days |
| Basic | 90 days | 90 days |
| Agency | 180+ days | 180+ days |
| Enterprise | Contract | Contract |

Retention jobs must delete or aggregate old time-series data according to plan.

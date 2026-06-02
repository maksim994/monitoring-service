# Local Development Setup

## Цель

Локальное окружение должно позволять разработчику поднять backend, frontend, worker, scheduler, probe, PostgreSQL/TimescaleDB и Redis одной командой через Docker Compose.

## Предварительные Требования

- Docker и Docker Compose.
- PHP 8.2+ для backend tooling, если команды запускаются вне контейнера.
- Node.js LTS для frontend tooling, если команды запускаются вне контейнера.
- Composer.
- npm или pnpm.

## Ожидаемая Структура Репозитория

```text
backend/
frontend/
bitrix-module/
probe/
deploy/
docs/
```

Кодовые каталоги создаются на этапе skeleton. До этого документация фиксирует целевой контракт окружения.

## Services

| Service | Port | Purpose |
| --- | --- | --- |
| `api` | 8080 | Backend API and ingest API |
| `frontend` | 3000 | Web cabinet |
| `worker` | n/a | Ingest, metrics and notifications workers |
| `scheduler` | n/a | Probe scheduler |
| `probe` | 8082 | Local probe node and health endpoint |
| `postgres` | 5432 | Business DB |
| `timescaledb` | 5433 | Time-series DB or same PostgreSQL with extension |
| `redis` | 6379 | Queues, locks, rate limits |
| `nginx` | 8088 | Optional reverse proxy |
| `mailhog` | 8025 | Local email testing |

## Environment

Local `.env` should include:

```dotenv
APP_ENV=dev
APP_SECRET=change-me
DATABASE_URL=postgresql://monitoring:monitoring@postgres:5432/monitoring
TIMESCALE_URL=postgresql://monitoring:monitoring@timescaledb:5432/metrics
REDIS_URL=redis://redis:6379
JWT_SECRET=change-me
MODULE_SIGNATURE_WINDOW_SECONDS=300
MAILER_DSN=smtp://mailhog:1025
TELEGRAM_BOT_TOKEN=
PUBLIC_API_URL=http://localhost:8080
FRONTEND_URL=http://localhost:3000
```

## First Run Flow

1. Start services with Docker Compose.
2. Run backend migrations.
3. Seed demo user (`app:seed-demo` runs automatically in Docker Compose).
4. Open frontend at http://localhost:13000.
5. Sign in with demo account (see below).
6. Add site or use pre-created demo site.
7. Configure Bitrix module against local API.
8. Verify heartbeat in cabinet.

## Demo Account

| Field | Value |
| --- | --- |
| Email | `demo@monitoring.local` |
| Password | `Demo123456` |
| Organization | `Demo Organization` |
| Frontend | http://localhost:13000 |
| API | http://localhost:18080 |

Full details: `demo-account.md`.

Manual seed:

```bash
make seed-demo
```

## Development Quality Gates

- Backend tests pass.
- Frontend typecheck passes.
- OpenAPI file validates.
- JSON schemas validate sample payloads.
- Health endpoints return ok.

## Sample Payload Testing

During development keep sample payloads near schemas or tests:

- valid heartbeat;
- invalid signature request;
- metrics batch with numeric and string values;
- events batch with backup event;
- oversized payload.

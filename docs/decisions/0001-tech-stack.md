# ADR 0001: MVP Tech Stack

## Status

Accepted for MVP planning.

## Context

Monitoring Service состоит из SaaS/backend, web cabinet, Bitrix-модуля, worker-процессов, probe-нод и self-hosted preview. Для MVP важнее скорость разработки, предсказуемость эксплуатации и близость к Bitrix/PHP-экосистеме, чем максимальная масштабируемость.

## Decision

### Backend

Использовать PHP 8.2+ и Symfony.

Причины:

- PHP-команда сможет лучше переиспользовать знания из Bitrix-экосистемы.
- Symfony даёт зрелые компоненты для HTTP, DI, Messenger, Validator, Security и Console.
- Проще поддерживать shared domain language между backend и Bitrix-модулем.
- Для MVP ожидаемая нагрузка не требует Go/Rust.

### Frontend

Использовать React + TypeScript + Vite.

Причины:

- Быстрое создание SPA-кабинета.
- Хорошая экосистема таблиц, форм, графиков и state management.
- TypeScript снижает риск расхождения с API-контрактами.

### Bitrix Module

Использовать PHP, совместимый с Bitrix main module и PHP 8.1+ где возможно.

Минимальный production target уточнить после discovery на реальных клиентах. Код модуля должен избегать современных возможностей PHP, если они ломают legacy Bitrix-окружения.

### Storage

- PostgreSQL 16 для бизнес-данных.
- TimescaleDB extension для time-series таблиц `metrics` и `probe_results`.
- Redis 7 для queues, locks, rate limits и cache.
- Object storage S3-compatible для отчётов и экспортов на следующих этапах.

### Queues

Для MVP использовать Symfony Messenger + Redis transport.

Расширение:

- RabbitMQ при росте очередей и необходимости routing/DLQ.
- Kafka только при больших объёмах ingest и event streaming.

### Deployment

- Docker Compose для local/dev и self-hosted preview.
- SaaS production на Docker images за reverse proxy.
- Kubernetes отложить до post-MVP.

### Observability

- Structured JSON logs.
- Prometheus-compatible metrics endpoint.
- Grafana dashboards для SaaS.
- Correlation/request IDs во всех сервисах.

## Alternatives Considered

### Laravel Вместо Symfony

Laravel ускоряет CRUD и admin-like flows, но Symfony лучше подходит для долгоживущих worker-процессов, строгой архитектуры, Messenger и enterprise-style self-hosted.

### Go Backend

Go хорош для ingest/probe сервисов, но увеличит технологическое расхождение с Bitrix/PHP и усложнит найм/поддержку MVP-команды. Можно вернуться к Go для probe service после beta.

### ClickHouse Сразу

ClickHouse лучше для больших объёмов метрик, но для MVP PostgreSQL + TimescaleDB проще в self-hosted, backup/restore и разработке.

### RabbitMQ Сразу

RabbitMQ даёт более зрелую очередь, но Redis достаточно для MVP и уже нужен для rate limits/cache. Перейти на RabbitMQ можно без изменения внешних API-контрактов.

## Consequences

- Backend и Bitrix-модуль пишутся на PHP, но должны иметь независимые слои и контракты.
- API-контракты фиксируются через OpenAPI и JSON Schema, а не через PHP-классы.
- Time-series данные остаются в PostgreSQL-экосистеме до появления реальных объёмов, требующих ClickHouse.
- Docker Compose становится обязательным артефактом для разработки и self-hosted preview.
- Нужны строгие performance limits для module collectors, потому что PHP-модуль выполняется в клиентском окружении.

## Initial Service Layout

```text
backend/
frontend/
bitrix-module/
probe/
deploy/
docs/
```

Физическое создание кодовых каталогов выполняется на этапе реализации skeleton, после утверждения контрактов.

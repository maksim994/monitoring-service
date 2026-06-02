# Сервис мониторинга сайтов 1С-Битрикс

Пакет проектной документации для создания сервиса мониторинга сайтов на 1С-Битрикс и коробочных порталов Битрикс24.

## Документы

| Документ | Назначение |
| --- | --- |
| `prd.md` | Продуктовые требования: аудитории, сценарии, MVP, тарифы, UX кабинета |
| `system-design.md` | Архитектура SaaS/backend: API, БД, очереди, probe-ноды, алерты, уведомления |
| `bitrix-module-design.md` | Архитектура Bitrix-модуля: установка, collectors, очередь, безопасность, module-only |
| `mvp-estimate.md` | Оценка сроков, команды, бюджета, рисков и этапов запуска |
| `docs/README.md` | Рабочая документация разработки: roadmap, API, БД, безопасность, модуль, beta |

## Быстрый Старт

Для разработки начинайте с документов:

1. `docs/roadmap.md` — этапы реализации MVP.
2. `docs/scope/mvp.md` — точные границы MVP.
3. `docs/decisions/0001-tech-stack.md` — выбранный технический стек.
4. `docs/api/openapi.yaml` — контракт API.
5. `docs/development/local-setup.md` — локальный запуск.

### Запуск dev-окружения

```bash
make up
```

Сервисы:

- API: http://localhost:18080
- Frontend: http://localhost:13000
- Probe health: http://localhost:18082/health/live
- Mailhog: http://localhost:18025

### Демо-аккаунт

| Поле | Значение |
| --- | --- |
| Email | `demo@monitoring.local` |
| Password | `Demo123456` |

Подробнее: `docs/development/demo-account.md`. Аккаунт создаётся автоматически при `make up`.

### Production (Coolify)

Инструкция по деплою на VPS: [`docs/deployment/coolify.md`](docs/deployment/coolify.md).

Стек: `docker-compose.yaml` в корне репозитория, один домен для кабинета и API (`/api/*`).

Структура кода:

```text
backend/         Symfony API
frontend/        React cabinet
bitrix-module/   Bitrix module skeleton
probe/           Probe node skeleton
deploy/          Docker Compose
docs/            Project documentation
```

## Концепция

Продукт состоит из трёх вариантов поставки:

- **SaaS**: основной сценарий, где Bitrix-модуль отправляет технические метрики в облако, а внешние probe-ноды проверяют доступность сайта.
- **Self-hosted / enterprise**: серверная часть разворачивается в инфраструктуре клиента.
- **Module-only**: упрощённый локальный режим без облачного кабинета, с проверками внутри Bitrix и прямыми уведомлениями.

Ключевое отличие от простого uptime-сервиса — глубокая диагностика Bitrix: агенты, cron, резервные копии, обновления, лицензия, диск, кеш, БД, почтовая очередь и состояние внутренних механизмов сайта.

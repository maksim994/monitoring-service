# Monitoring Service Documentation

Этот каталог содержит рабочую документацию для разработки сервиса мониторинга сайтов 1С-Битрикс и коробочных порталов Битрикс24.

## Быстрый Старт Для Команды

1. Прочитать продуктовый контекст в `../prd.md`.
2. Сверить границы MVP в `scope/mvp.md`.
3. Принять технические решения из `decisions/0001-tech-stack.md`.
4. Использовать `api/openapi.yaml` и `api/payload-schemas/` как контракт между backend и Bitrix-модулем.
5. Запускать локальное окружение по `development/local-setup.md`.

## Структура Документации

| Раздел | Назначение |
| --- | --- |
| `roadmap.md` | Этапы разработки от подготовки до публичного MVP |
| `scope/` | Scope MVP, out-of-scope и критерии готовности |
| `decisions/` | Architecture Decision Records |
| `api/` | OpenAPI и JSON Schema payload-ов |
| `database/` | Схема данных, индексы, tenant scope |
| `alerts/` | Правила алертов, debounce, fingerprint |
| `notifications/` | Каналы уведомлений и retry policy |
| `bitrix-module/` | Установка, collectors, очередь, передаваемые данные |
| `security/` | HMAC, replay protection, SSRF, privacy |
| `development/` | Локальная разработка и workflow |
| `development/demo-account.md` | Демо-учётная запись для local/dev |
| `deployment/` | Docker Compose, Coolify и self-hosted preview |
| `deployment/coolify.md` | Production-деплой на VPS через Coolify |
| `operations/` | Health checks, observability, backup/restore |
| `checks/` | Каталог проверок и probe behavior |
| `incidents/` | Lifecycle инцидентов |
| `user-guide/` | Пользовательские инструкции |
| `admin/` | Роли и права |
| `billing/` | Тарифы и лимиты |
| `support/` | Troubleshooting |
| `qa/` | План тестирования |
| `marketplace/` | Подготовка Bitrix Marketplace |
| `research/` | Discovery и технические спайки |
| `performance/` | Ограничения нагрузки модуля |
| `beta/` | План закрытой beta |

## Правило Актуальности

Документы в `api/`, `database/`, `security/` и `bitrix-module/` считаются контрактными. Любое изменение поведения реализации должно сопровождаться обновлением соответствующего документа.

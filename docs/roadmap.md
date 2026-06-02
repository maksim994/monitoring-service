# Roadmap MVP

Документ описывает порядок разработки Monitoring Service от подготовки до публичного MVP.

## Цель MVP

Подтвердить, что Bitrix-интеграторы и владельцы сайтов готовы подключать модуль, получать точные инциденты и использовать сервис как рабочий инструмент технического сопровождения.

Ключевой результат MVP: пользователь подключает первый сайт не дольше чем за 15 минут, получает heartbeat в течение 2 минут и видит реальные инциденты по uptime, SSL, диску, бэкапам, обновлениям и агентам.

## Этапы

| Этап | Срок | Результат |
| --- | --- | --- |
| 0. Подготовка | 1-2 недели | Утверждены scope, стек, риски и структура документации |
| 1. Discovery | 2-3 недели | Проверены Bitrix collectors, HMAC ingest, probe-ноды и нагрузка |
| 2. API и данные | 2-3 недели | Зафиксированы OpenAPI, JSON Schema, БД и lifecycle инцидентов |
| 3. Каркас продукта | 4-6 недель | Пользователь добавляет сайт, модуль отправляет heartbeat |
| 4. Проверки и инциденты | 5-7 недель | Метрики пишутся, алерты создают и закрывают инциденты |
| 5. Уведомления и beta UX | 3-5 недель | Telegram/email/webhook, роли, проекты, тарифные лимиты |
| 6. Stabilization | 3-5 недель | Security review, QA, observability, Marketplace readiness |

## Критический Путь

1. Подтвердить collectors на реальных Bitrix-проектах.
2. Зафиксировать HMAC module-to-api и replay protection.
3. Реализовать локальную очередь модуля с retry/backoff.
4. Запустить ingest API и worker.
5. Запустить external probes минимум из двух независимых окружений.
6. Реализовать alert engine без дублей.
7. Довести Telegram/email delivery до стабильного состояния.

## Порядок Первых Спринтов

### Sprint 1

- Подготовить `docs/` и контрактную документацию.
- Зафиксировать MVP scope и tech stack.
- Сформировать draft OpenAPI и JSON Schema для ingest.
- Подготовить план Bitrix collector spike.

### Sprint 2

- Реализовать минимальный backend skeleton.
- Реализовать минимальный Bitrix module skeleton.
- Проверить handshake и heartbeat end-to-end.
- Поднять Docker Compose для dev.

### Sprint 3

- Добавить site keys, local queue и retry.
- Добавить первые collectors: environment и disk.
- Добавить basic dashboard shell и site list.
- Начать фиксацию результатов discovery.

## Definition Of Done Для MVP

- Ingest API принимает подписанные heartbeat и metrics batch.
- Сайты, организации, пользователи и роли заведены в БД.
- Модуль устанавливается, отправляет heartbeat, собирает MVP collectors и ретраит отправку.
- Внешние probe-ноды выполняют HTTP и SSL checks.
- Alert engine создаёт, обновляет и закрывает инциденты без дублей.
- Telegram, email и webhook уведомления отправляются через очередь.
- Кабинет показывает dashboard, сайты, карточку сайта, инциденты и настройки уведомлений.
- Тарифные лимиты применяются минимум по количеству сайтов и частоте проверок.
- Есть документация установки, troubleshooting и состава передаваемых данных.

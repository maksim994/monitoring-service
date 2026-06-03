# MVP Scope

## Цель MVP

MVP должен доказать, что сервис решает практическую задачу сопровождения Bitrix-проектов: заранее обнаруживает технические риски, создаёт понятные инциденты и доставляет уведомления без лишнего шума.

## Входит В MVP

### Аккаунты И Организации

- Регистрация и вход пользователей.
- Организации как tenant boundary.
- Базовые роли: Owner, Admin, Integrator, Operator, Viewer.
- Пользователи организации и управление ролями.

### Сайты

- Добавление сайта по домену и URL.
- Генерация `site_id` и `api_secret`.
- Статус подключения.
- Последний heartbeat.
- Версия Bitrix-модуля.
- Ротация ключа подключения.
- Отключение сайта без удаления истории.

### Bitrix-Модуль

- Установка и удаление стандартными средствами Bitrix.
- Настройки подключения: mode, API URL, site ID, API secret.
- Test connection и тестовый heartbeat.
- HMAC-подпись запросов.
- Локальная очередь payload-ов.
- Retry/backoff при сетевых сбоях.
- Remote config fallback.
- Collectors MVP: environment, disk, backup, modules, agents.

### Ingest API

- `POST /api/v1/sites/handshake`.
- `POST /api/v1/heartbeat`.
- `POST /api/v1/metrics/batch`.
- `POST /api/v1/events/batch`.
- `GET /api/v1/module/config`.
- Проверка подписи, timestamp и replay protection.
- Постановка событий в очередь.

### Внешние Проверки

- HTTP/HTTPS uptime главной страницы.
- HTTP/HTTPS uptime произвольного URL.
- SSL expiry и handshake status.
- Domain expiry best-effort через WHOIS/RDAP, если доступно.
- Минимум две probe-ноды для подтверждения critical uptime.

### Инциденты

- Создание инцидента по alert rule.
- Deduplication через fingerprint.
- Статусы: open, acknowledged, resolved, muted.
- Severity: info, warning, critical.
- Evidence и история событий.
- Автоматическое закрытие при восстановлении.
- Comments.
- Mute проверки.
- Maintenance windows.

### Уведомления

- Telegram.
- Email.
- Webhook.
- Тестовое уведомление.
- Delivery log.
- Retry policy.
- Разные каналы для warning и critical.
- Повтор critical-уведомлений по интервалу.

### Кабинет

- Общий dashboard.
- Список сайтов.
- Карточка сайта.
- Список инцидентов.
- Карточка инцидента.
- Настройки проверок.
- Каналы уведомлений.
- Пользователи и роли.
- Тариф и лимиты.

### Тарифные Лимиты

- Free, Basic, Agency, Enterprise как доменная модель.
- Применение лимитов минимум по числу сайтов, частоте проверок, числу пользователей и webhooks.
- Без реальной онлайн-оплаты в MVP.

### Self-hosted Preview

- Docker Compose для backend, frontend, worker, scheduler, probe, PostgreSQL/TimescaleDB, Redis, nginx.
- Seed admin user.
- Отключаемый billing.
- Документация запуска.

## Не Входит В MVP

- SMS и голосовые звонки.
- White-label.
- SSO/SAML/OIDC.
- Kubernetes-поставка.
- Полноценный recurring billing.
- Сложные browser-сценарии.
- Deep APM и профилирование PHP-кода.
- Автоматическое исправление проблем на сайте.
- Хранение персональных данных пользователей сайта.
- Анализ содержимого заказов, форм, файлов `/upload/`, cookie и сессий.
- Полноценное enterprise licensing для self-hosted.
- Мобильное приложение.

## Дефолтные Проверки И Пороги

| Проверка | Warning | Critical |
| --- | --- | --- |
| Uptime | 1 ошибка после debounce | 2 ошибки подряд или 2 probe-ноды |
| SSL expiry | Менее 14 дней | Менее 3 дней или handshake failed |
| Domain expiry | Менее 30 дней | Менее 7 дней |
| Disk free | Менее 15% | Менее 5% |
| Backup age | Старше 3 дней | Старше 7 дней |
| Agents lag | Больше 30 минут | Больше 2 часов |
| Bitrix license / support | Менее 30 дней | Менее 7 дней |
| Heartbeat missing | Старше 15 минут | Старше 30 минут |

Пороги должны быть настраиваемыми на уровне сайта или шаблона правил.

## Критерии Готовности MVP

- Первый сайт подключается не дольше чем за 15 минут.
- Первый heartbeat приходит в течение 2 минут после настройки модуля.
- Неподписанные и просроченные ingest-запросы отклоняются.
- Модуль не создаёт заметной нагрузки на Bitrix-сайт.
- Alert engine не создаёт дубликаты активных инцидентов.
- Critical uptime подтверждается debounce-правилом.
- Telegram и email доставляют critical-уведомления менее чем за 60 секунд после открытия инцидента.
- Dashboard открывается с P95 до 2 секунд на организации со 100 сайтами.
- Ложные critical-инциденты ниже 2-5% на beta.
- Пользователь видит страницу состава передаваемых данных.
- Есть документация установки и troubleshooting.

## Beta Scope

Закрытая beta проводится на 5-10 сайтах:

- свежий Bitrix-проект;
- legacy Bitrix;
- интернет-магазин;
- коробочный Битрикс24;
- shared-hosting;
- сайт с большим `/upload/`;
- сайт с агентами на cron;
- сайт с агентами на hit-режиме.

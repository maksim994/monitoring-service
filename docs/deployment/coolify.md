# Развёртывание в Coolify

Инструкция для production/preview-деплоя [Monitoring Service](https://github.com/maksim994/monitoring-service) на VPS с [Coolify](https://coolify.io).

## Что получится

Один домен (например `https://monitoring.example.com`):

| Путь | Сервис |
| --- | --- |
| `/` | React-кабинет (клиент + `/admin`) |
| `/api/*` | Symfony API |

Внутри стека (без публичных портов):

- PostgreSQL 16
- Redis 7
- API + worker + scheduler
- Probe-нода

Файлы деплоя:

```text
deploy/docker-compose.prod.yml   production stack
deploy/Dockerfile.web            nginx + собранный frontend
deploy/nginx/default.conf        прокси /api -> api:8080
deploy/.env.production.example   шаблон переменных
```

## Требования к серверу

| Параметр | Минимум | Рекомендуется |
| --- | --- | --- |
| CPU | 2 vCPU | 4 vCPU |
| RAM | 2 GB | 4 GB |
| Disk | 20 GB SSD | 40 GB SSD |
| OS | Ubuntu 22.04/24.04 | Ubuntu 24.04 LTS |

На сервере должен быть установлен Coolify (v4.x).

## Шаг 1. Загрузить код в GitHub

Репозиторий: [maksim994/monitoring-service](https://github.com/maksim994/monitoring-service)

Если репозиторий пустой — сначала запушьте код:

```bash
cd /path/to/monitoring-service
git init
git remote add origin git@github.com:maksim994/monitoring-service.git
git add .
git commit -m "Initial MVP"
git branch -M main
git push -u origin main
```

Не коммитьте `.env` с секретами — только `deploy/.env.production.example`.

## Шаг 2. Подключить GitHub к Coolify

1. Coolify → **Sources** → **GitHub App**.
2. Авторизуйте доступ к организации/аккаунту `maksim994`.
3. Убедитесь, что репозиторий `monitoring-service` доступен.

## Шаг 3. Создать проект и ресурс

1. **Projects** → **+ Add** → назовите, например, `Monitoring Service`.
2. Выберите окружение **Production** (или **Preview** для теста).
3. **+ New Resource** → **Docker Compose**.
4. Заполните:

| Поле | Значение |
| --- | --- |
| Repository | `maksim994/monitoring-service` |
| Branch | `main` |
| Base Directory | `/` (корень репозитория) |
| Docker Compose Location | `/docker-compose.yaml` |

> **Важно:** Coolify не поддерживает compose-файлы только с `include:` — сервисы должны быть описаны прямо в `docker-compose.yaml` в корне репозитория.  
> Не используйте `/deploy/docker-compose.prod.yml` — будет ошибка *format is invalid* или *no service selected*.

Альтернатива (если нужен файл из `deploy/`):

| Поле | Значение |
| --- | --- |
| Base Directory | `/deploy` |
| Docker Compose Location | `/docker-compose.prod.yml` |

5. Сохраните ресурс.

## Шаг 4. Переменные окружения

Coolify → ваш ресурс → **Environment Variables**.

Скопируйте из `deploy/.env.production.example` и замените placeholder-значения:

| Переменная | Описание |
| --- | --- |
| `APP_SECRET` | Случайная строка 32+ символов |
| `POSTGRES_PASSWORD` | Пароль PostgreSQL |
| `INTERNAL_API_TOKEN` | Токен для probe/scheduler → API |
| `FRONTEND_URL` | Публичный URL кабинета, напр. `https://monitoring.example.com` |
| `PLATFORM_ADMIN_EMAIL` | Email platform admin |
| `PLATFORM_ADMIN_PASSWORD` | Пароль platform admin (для seed после деплоя) |
| `MAILER_DSN` | SMTP для email-уведомлений (опционально) |
| `TELEGRAM_BOT_TOKEN` | Telegram-бот (опционально) |

Генерация секретов:

```bash
openssl rand -hex 32
```

## Шаг 5. Домен и HTTPS

1. В ресурсе откройте сервис **`web`**.
2. **Domains** → добавьте домен, напр. `monitoring.example.com`.
3. Port: **80** (внутренний порт контейнера nginx).
4. Включите **Generate SSL** (Let's Encrypt).
5. В DNS создайте A-запись домена на IP сервера Coolify.

Coolify проксирует HTTPS → контейнер `web:80`. API доступен по тому же домену через `/api`.

## Шаг 6. Deploy

1. Нажмите **Deploy**.
2. Дождитесь сборки образов (`api`, `web`, `probe`) и старта контейнеров.
3. Проверьте логи сервиса **`api`**: миграции должны пройти без ошибок.

Первый деплой может занять 5–10 минут (сборка frontend + PHP-образов).

## Шаг 7. Создать platform admin

После успешного деплоя выполните seed администратора платформы.

Coolify → сервис **`api`** → **Terminal** (или SSH на сервер):

```bash
php bin/console app:seed-platform-admin --env=prod
```

Войдите в кабинет:

- URL: `https://monitoring.example.com/admin`
- Email/пароль — из `PLATFORM_ADMIN_EMAIL` / `PLATFORM_ADMIN_PASSWORD`

Клиентский кабинет: `https://monitoring.example.com` — регистрация новых организаций через форму «Создать аккаунт».

## Проверка после деплоя

| Проверка | Ожидание |
| --- | --- |
| `https://your-domain/` | Страница входа в кабинет |
| `https://your-domain/admin` | Platform Admin (после seed) |
| Логи `worker` | `messenger:consume` без fatal errors |
| Логи `scheduler` | Периодический `app:run-probes` |
| Логи `probe` | Успешные запросы к `api:8080` |
| `https://your-domain/health/live` | JSON `{"status":"ok"}` |

Smoke-тест с сервера (опционально):

```bash
curl -fsS https://monitoring.example.com/ | head
curl -fsS https://monitoring.example.com/health/live
curl -fsS -o /dev/null -w "%{http_code}\n" https://monitoring.example.com/api/v1/auth/login -X POST -H 'Content-Type: application/json' -d '{}'
# login без тела → 400/422, не 502
```

## Подключение Bitrix-модуля

В настройках модуля на сайте клиента укажите:

| Параметр | Значение |
| --- | --- |
| API URL | `https://monitoring.example.com` |
| Site ID / API Secret | Из кабинета после добавления сайта |

Модуль отправляет heartbeat и метрики на ingest API того же домена.

## Обновление версии

1. Push в ветку `main`.
2. Coolify → **Redeploy** (или включите auto-deploy on push).

При обновлении миграции применяются автоматически при старте контейнера `api`. Миграции **добавляют** схему, но **не удаляют** пользователей, сайты и организации.

## Сохранение данных при redeploy

### Что уже настроено в проекте

PostgreSQL использует **named volume** `postgres_data` с фиксированным именем `monitoring_postgres_data`. При обычном **Redeploy** контейнеры пересобираются, а данные БД остаются на диске сервера.

Redis **без volume** — очередь сообщений может обнулиться при перезапуске Redis. Это не стирает организации/сайты в PostgreSQL, но необработанные задачи в очереди могут потеряться.

### Что сделать в Coolify

1. **Redeploy**, а не удаление ресурса  
   Кнопка **Redeploy** / auto-deploy on push — безопасно.  
   **Delete resource** или `docker compose down -v` — **сотрёт volume** и все данные.

2. **Не меняйте секреты «просто так» после первого деплоя**

   | Переменная | Если изменить |
   | --- | --- |
   | `POSTGRES_PASSWORD` | PostgreSQL не поднимется со старым volume (пароль уже записан в data dir) |
   | `APP_SECRET` | Сломается расшифровка секретов сайтов (API keys) |
   | `INTERNAL_API_TOKEN` | Probe перестанет слать данные, пока не обновите токен везде |

3. **Проверьте Persistent Storage** (опционально)  
   Coolify → сервис **postgres** → **Persistent Storage** — должен быть mount на `/var/lib/postgresql/data`.  
   Если Coolify показывает лишние anonymous volumes после каждого деплоя — обновите compose из репозитория (volume с `name: monitoring_postgres_data`).

4. **Бэкапы** — даже при правильных volume делайте `pg_dump` по расписанию (см. ниже).

### Что безопасно при каждом деплое

- Обновление кода (git push → redeploy)
- Пересборка образов `api`, `web`, `worker`, `probe`
- `doctrine:migrations:migrate` при старте `api`
- Изменение `FRONTEND_URL`, SMTP, Telegram — **не** стирает БД

### Что может «обнулить» кабинет

- Удаление приложения/стека в Coolify **с volumes**
- Смена `POSTGRES_PASSWORD` без переинициализации БД
- Пересоздание ресурса с другим UUID (новый anonymous volume)
- Ручной `docker volume rm monitoring_postgres_data`

## Бэкапы

Минимум для production:

1. **PostgreSQL** — volume `postgres_data`. Настройте snapshot или `pg_dump` по cron.
2. **Секреты** — храните копию env-переменных Coolify вне репозитория.

Пример дампа (на сервере Coolify):

```bash
docker exec -t $(docker ps -qf name=postgres) pg_dump -U monitoring monitoring > backup.sql
```

## Troubleshooting

### «no service selected»

Coolify не разворачивает compose-файлы, где сервисы подключены только через `include:`.  
**Решение:** используйте `/docker-compose.yaml` из корня репозитория (полный стек, без `include`).

### «Docker compose location field format is invalid»

Coolify не принимает некоторые пути с подпапками при Base Directory `/`.

**Решение:** оставьте Base Directory `/` и Docker Compose Location `/docker-compose.yaml`.

### 502 Bad Gateway на домене

- Проверьте, что контейнер `web` running.
- Проверьте, что `api` поднялся (миграции не упали).
- В Coolify домен привязан к сервису **`web`**, port **80**.

### API отвечает, но кабинет не логинится

- `FRONTEND_URL` должен совпадать с публичным URL (с `https://`).
- Frontend собран с пустым `VITE_API_URL` — запросы идут на тот же домен (`/api/...`).

### CORS errors

При одном домене через nginx CORS не нужен для кабинета. Если выносите API на отдельный поддомен — задайте:

```dotenv
CORS_ALLOW_ORIGIN='^https://monitoring\.example\.com$'
```

и пересоберите frontend с `VITE_API_URL=https://api.example.com`.

### Worker не обрабатывает очередь

- Redis должен быть healthy.
- Проверьте логи `worker`: `messenger:consume async`.

### Не создался platform admin

Команда идемпотентна — выполните в контейнере **`api`**, **`worker`** или **`scheduler`**:

```bash
php bin/console app:seed-platform-admin --env=prod
```

### «Unable to read /app/.env»

Symfony требует файл `.env` внутри Docker-образа backend. Он должен быть в репозитории. После обновления — **Redeploy** с пересборкой образов.

## Альтернатива: отдельные домены для API и frontend

Для MVP рекомендуется один домен (проще TLS и CORS). Если нужны два домена:

1. Опубликуйте `api` отдельно (в Coolify — второй domain на сервис `api:8080`).
2. Соберите frontend с `VITE_API_URL=https://api.example.com`.
3. Настройте `CORS_ALLOW_ORIGIN` под домен кабинета.

## Связанные документы

- `docker-compose.md` — описание сервисов
- `../development/local-setup.md` — локальная разработка
- `../development/demo-account.md` — демо только для dev
- `../admin/rbac.md` — роли platform admin

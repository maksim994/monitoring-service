# Demo Account

Демо-учётная запись создаётся автоматически при запуске dev-окружения через Docker Compose.

## Учётные Данные

| Поле | Значение |
| --- | --- |
| Email | `demo@monitoring.local` |
| Password | `Demo123456` |
| Organization | `Demo Organization` |

## URL

| Сервис | URL |
| --- | --- |
| Frontend (кабинет) | http://localhost:13000 |
| API | http://localhost:18080 |

## Демо-Сайт

При первом seed также создаётся тестовый сайт:

| Поле | Значение |
| --- | --- |
| Domain | `demo.example.ru` |
| Site URL | `https://demo.example.ru` |

`siteId` и `apiSecret` выводятся в лог команды seed. Их можно посмотреть так:

```bash
docker compose -f deploy/docker-compose.yml exec api php bin/console app:seed-demo
```

Если аккаунт уже существует, команда ничего не пересоздаёт.

## Ручной Seed

```bash
make seed-demo
```

или

```bash
docker compose -f deploy/docker-compose.yml exec api php bin/console app:seed-demo
```

## Безопасность

- Использовать только в `APP_ENV=dev`.
- Не применять эти credentials в production.
- Перед публичным деплоем отключить автоматический seed.

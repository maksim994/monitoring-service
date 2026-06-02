# Backup And Restore

## Область MVP

Документ относится к self-hosted preview и внутренней SaaS эксплуатации. Полноценные enterprise backup policies уточняются после MVP.

## Что Нужно Бэкапить

- PostgreSQL business database.
- TimescaleDB metrics database.
- Object storage, если используется.
- `.env` и secrets вне репозитория.
- Release version metadata.

## Что Не Нужно Бэкапить

- Redis queue как единственный источник правды не должен использоваться для долговременных данных.
- Temporary files.
- Build artifacts, если есть release images.

## Self-hosted Preview Backup

Рекомендуемый базовый подход:

1. Остановить write-heavy workers или перевести сервис в maintenance.
2. Сделать `pg_dump` business DB.
3. Сделать `pg_dump` TimescaleDB или volume snapshot.
4. Сохранить `.env` в защищённое хранилище клиента.
5. Проверить наличие release image tags.

## Restore Flow

1. Развернуть ту же версию Docker images.
2. Восстановить `.env`.
3. Восстановить PostgreSQL.
4. Восстановить TimescaleDB.
5. Запустить migrations, если версия требует.
6. Запустить services.
7. Проверить `/health/ready`.
8. Проверить вход admin user.
9. Проверить последний heartbeat сайтов.

## Recovery Targets MVP

Self-hosted preview:

- RPO: 24 часа.
- RTO: 4 часа.

SaaS beta:

- RPO: 4 часа.
- RTO: 2 часа.

## Security

- Backup files must be encrypted at rest.
- Backup access is restricted to admins.
- Backup logs must not contain secrets.
- Restore operations must be recorded in audit log for SaaS.

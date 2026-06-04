# Changelog

## Unreleased

### Fixed

- SSL probe: certificate parsing on PHP 8+ (`OpenSSLCertificate`), retry without chain verify for expiry read.
- Domain probe: WHOIS fallback for `.ru` / `.su` when RDAP returns no expiration.


### Added

- В карточках проверок блок «Сейчас»: актуальные даты SSL, домена, лицензии Bitrix, бэкапа, диска и др. (snapshot в API).
- Проверка `bitrix_license_expiry`: срок лицензии и техподдержки 1С-Битрикс из API модуля (`license.days_left`).
- Карточка сайта: пояснения к проверкам и окну обслуживания, настройка порогов (диск, бэкап, agents, SSL, домен, модули, лицензия).
- API `PATCH /api/v1/sites/{siteId}/checks/{checkId}` — пороги в `settings` проверки.
- Термин «Связь с модулем Bitrix» вместо heartbeat в кабинете и уведомлениях.

- Probe check `domain_expiry` (RDAP best-effort) with incidents for domains expiring within 30/7 days.
- Module metric `backup.age_hours` and `backup_stale` incidents (warning 3d / critical 7d).
- `BackupCollector`, `AgentsCollector`, `ModulesCollector` in Bitrix module.
- Incidents for `agents_lag` (lag > 30 min / 2 h) and `modules_updates` (available updates).
- `app:provision-checks` adds missing checks to existing sites.
- Telegram bot token per channel, SMTP error hints, disk incident bytes, `TELEGRAM_PROXY_URL`, `MAILER_FROM` resolver.
- Повтор critical-инцидентов в Telegram (`app:dispatch-critical-reminders`, интервал `CRITICAL_TELEGRAM_REMINDER_SECONDS`).
- Maintenance windows: API и UI на карточке сайта, подавление новых инцидентов и Telegram-напоминаний.

- Development documentation structure under `docs/`.
- MVP roadmap, scope, tech stack ADR, API contracts and JSON schemas.
- Database schema, alert rules, notification channel documentation.
- Bitrix module installation, collectors, queue and transmitted data docs.
- Security documentation for HMAC, SSRF and privacy.
- DevOps, health check, observability, QA, beta and Marketplace docs.
- Symfony 7.4 backend skeleton with auth, sites, ingest API and HMAC verification.
- React frontend skeleton with login/register and site management dashboard.
- Docker Compose dev stack in `deploy/docker-compose.yml`.
- Bitrix module skeleton in `bitrix-module/`.
- Probe service skeleton in `probe/`.

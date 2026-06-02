# QA Test Plan

## Test Areas

### Backend

- Auth and RBAC.
- Organization tenant isolation.
- Site creation and key rotation.
- HMAC ingest.
- Queue processing.
- Alert rule evaluation.
- Incident lifecycle.
- Notification delivery.
- Plan limits.

### Bitrix Module

- Install/uninstall.
- Options page.
- Test connection.
- Heartbeat.
- Local queue retry/backoff.
- Collectors on supported environments.
- Remote config fallback.
- Module-only base checks.

### Frontend

- Login.
- Dashboard.
- Add site flow.
- Site list filters.
- Site card.
- Incident list and card.
- Notification channels.
- Users and roles.

### Probe

- HTTP success/failure.
- SSL expiry.
- DNS failure.
- SSRF blocked URLs.
- Timeout behavior.

## Required Test Stands

- Fresh Bitrix with PHP 8.2.
- Legacy Bitrix.
- Boxed Bitrix24.
- Shared-hosting style limits.
- Site with large `/upload/`.
- Site with agents on cron.
- Site with agents on hit mode.

## Release Gate

Public MVP requires:

- no critical install/uninstall defects;
- no HMAC bypass;
- no tenant isolation defects;
- no high severity SSRF findings;
- successful delivery of critical Telegram/email notifications;
- documented known limitations.

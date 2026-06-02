# Probe Spike

## Цель

Проверить внешний uptime/SSL/domain monitoring из независимых probe-нод.

## Проверки

- HTTP 200 success.
- HTTP 500 failure.
- Timeout.
- DNS NXDOMAIN.
- SSL valid.
- SSL expired/self-signed.
- Redirect behavior.
- SSRF blocked private URL.

## Probe Locations

MVP target: минимум 2 независимые ноды.

Examples:

- EU node.
- RU/KZ node.

## Что Фиксировать

- response time;
- status;
- error code;
- evidence shape;
- false failures;
- DNS resolution behavior;
- SSL library errors.

## Success Criteria

- Critical uptime подтверждается двумя источниками или двумя последовательными ошибками.
- SSRF denylist работает.
- SSL expiry корректно рассчитывается.
- Probe result отправляется в ingest API.

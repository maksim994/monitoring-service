# Notification Channels

## Назначение

Notification service доставляет сообщения об инцидентах в Telegram, email и webhook, хранит историю попыток и ретраит временные ошибки.

## Каналы MVP

### Telegram

Required settings:

- bot token;
- chat id;
- channel name.

Требования:

- Поддержать тестовое уведомление.
- Обрабатывать ошибки `blocked`, `chat_not_found`, `migrated`.
- Не логировать bot token.

### Email

Required settings:

- recipient email;
- sender config from environment or provider settings;
- channel name.

Требования:

- HTML и text версия письма.
- DKIM/SPF на домене сервиса для SaaS.
- Маскировать provider errors, если они содержат адреса или токены.

### Webhook

Required settings:

- URL;
- optional secret for outgoing signature;
- channel name.

Требования:

- POST JSON.
- HMAC signature for outgoing webhook.
- Retry on 5xx and 429.
- Do not retry most 4xx.
- SSRF protection для URL.

## Delivery Payload

```json
{
  "event": "incident.opened",
  "incidentId": "inc_123",
  "siteId": "site_123",
  "severity": "critical",
  "status": "open",
  "checkType": "uptime_http",
  "title": "Site is unavailable",
  "openedAt": "2026-06-02T11:00:00Z",
  "evidence": {
    "httpCode": 500,
    "probeIds": ["eu-1", "ru-1"]
  }
}
```

## Retry Policy

| Attempt | Delay |
| --- | --- |
| 1 | Immediately |
| 2 | 1 minute |
| 3 | 5 minutes |
| 4 | 15 minutes |
| Final | Mark failed |

## Rate Limits

- Global organization notification limit.
- Per-channel limit.
- Group warning events of the same type when possible.
- Critical incidents are never silently dropped; they can be delayed and marked in delivery log.

## Routing Rules

MVP routing supports:

- organization-level channels;
- site-level channel overrides;
- severity filter: warning and critical;
- check type filter.

## Delivery Log

Every attempt creates or updates `notification_deliveries`:

- channel id;
- incident id;
- status;
- attempt number;
- sanitized error;
- sent timestamp;
- next attempt timestamp.

## Message Content

Messages should include:

- severity;
- site domain;
- incident title;
- start time;
- current evidence;
- action link to cabinet;
- short recommendation where available.

# Troubleshooting

## Module Does Not Connect

Check:

- API URL is reachable from Bitrix server.
- `site_id` matches cabinet.
- `api_secret` was copied correctly.
- Server time is synchronized.
- Site key is active.

## Invalid Signature

Likely causes:

- wrong secret;
- changed raw body before signing;
- server time drift;
- duplicate request id.

Actions:

- rotate key;
- check timestamp;
- check HMAC algorithm;
- inspect sanitized backend security logs.

## No Heartbeat

Check:

- Bitrix agent is registered;
- agents are running on cron or hits;
- local queue has pending/failed records;
- API URL is reachable;
- remote config is valid.

## Collector Failed

Check:

- local module log;
- collector timeout;
- Bitrix permissions;
- PHP extensions;
- legacy Bitrix API behavior.

Collector failure should not stop other collectors.

## Notifications Not Delivered

Check:

- channel enabled;
- severity routing;
- delivery log;
- provider error;
- Telegram bot blocked;
- webhook SSRF validation and response status.

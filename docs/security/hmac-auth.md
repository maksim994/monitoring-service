# HMAC Authentication

## Назначение

Bitrix-модуль подписывает все запросы к ingest API и remote config API. Backend проверяет подпись, timestamp и повторное использование request id.

## Headers

| Header | Required | Meaning |
| --- | --- | --- |
| `X-Site-Id` | yes | Site id from cabinet |
| `X-Timestamp` | yes | UTC ISO-8601 timestamp |
| `X-Signature` | yes | Hex HMAC-SHA256 |
| `X-Module-Version` | yes | Module version |
| `X-Request-Id` | yes | Unique request id |

## Signature

Для запросов с body:

```text
hex_hmac_sha256(api_secret, timestamp + "." + raw_body)
```

Для `GET /api/v1/module/config` без body:

```text
hex_hmac_sha256(api_secret, timestamp + "." + method + "." + path)
```

## Verification

Backend должен:

1. Найти сайт по `X-Site-Id`.
2. Проверить, что сайт активен.
3. Найти активный ключ сайта.
4. Проверить timestamp window.
5. Проверить request id на повтор.
6. Рассчитать signature на raw body.
7. Сравнить подпись constant-time сравнением.
8. Записать audit/security metric без секретов.

## Timestamp Window

MVP допускает расхождение времени до 5 минут.

Если timestamp устарел или слишком далеко в будущем:

- вернуть HTTP 401;
- error code: `signature_timestamp_invalid`;
- не ставить payload в очередь.

## Replay Protection

Backend хранит использованные `X-Request-Id` в Redis:

```text
module_request:{site_id}:{request_id}
```

TTL: 10 минут.

Повторный request id в окне TTL отклоняется с HTTP 401 и code `signature_replay_detected`.

## Secret Handling

- Full API secret показывается пользователю только один раз при создании site key.
- В backend хранить verifier: hash/encrypted value, пригодный для проверки выбранным способом.
- В Bitrix-модуле хранить secret encrypted.
- Secret никогда не писать в logs, queue payload, audit log и exception traces.

## Rotation

Rotation flow:

1. User creates new site key.
2. Backend keeps old and new key active during overlap window.
3. User updates module settings.
4. Module sends successful handshake with new key.
5. User or backend revokes old key.

## Error Codes

| Code | Meaning |
| --- | --- |
| `signature_missing_headers` | Required signature headers are missing |
| `signature_site_not_found` | Site id unknown |
| `signature_site_disabled` | Site disabled |
| `signature_key_revoked` | Key revoked |
| `signature_timestamp_invalid` | Timestamp outside allowed window |
| `signature_replay_detected` | Request id already used |
| `signature_invalid` | HMAC mismatch |

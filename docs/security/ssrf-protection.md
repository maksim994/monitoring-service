# SSRF Protection

## Область Применения

SSRF protection обязательна для:

- external HTTP checks;
- SSL checks;
- webhook delivery URLs;
- любых будущих пользовательских URL.

## Разрешённые Схемы

Только:

- `http`;
- `https`.

Запрещены:

- `file`;
- `ftp`;
- `gopher`;
- `dict`;
- `ldap`;
- custom schemes.

## Запрещённые Адреса

Запрещать connect к:

- localhost;
- loopback ranges;
- private IPv4 ranges;
- link-local ranges;
- multicast ranges;
- IPv6 localhost;
- IPv6 unique local addresses;
- metadata service addresses.

Минимальный denylist:

```text
127.0.0.0/8
10.0.0.0/8
172.16.0.0/12
192.168.0.0/16
169.254.0.0/16
::1/128
fc00::/7
fe80::/10
```

## DNS Resolve Правила

1. Resolve hostname перед запросом.
2. Проверить все A/AAAA адреса.
3. Запретить запрос, если хотя бы один resolved IP запрещён.
4. Перед connect повторно проверить IP, к которому фактически подключается клиент.
5. Не доверять только исходному hostname.

## Redirects

MVP default:

- redirects disabled for webhook;
- redirects optional for HTTP checks;
- каждый redirect target проходит полную SSRF-проверку;
- max redirects: 3.

## Ports

Разрешённые порты MVP:

- 80;
- 443.

Custom ports можно добавить позже с явным allowlist.

## Timeouts

- DNS timeout: 2 seconds.
- Connect timeout: 3 seconds.
- Total request timeout: 10 seconds.

## Logging

Логировать можно:

- normalized URL без credentials;
- hostname;
- error code;
- request id.

Не логировать:

- URL credentials;
- Authorization headers;
- webhook secrets;
- response body по умолчанию.

## Webhook Additional Rules

- Outgoing webhook подписывается HMAC, если задан secret.
- Не отправлять webhook на запрещённые IP даже в self-hosted по умолчанию.
- Для enterprise можно добавить admin-managed allowlist private ranges.

# Security Review Checklist

## Backend

- Все публичные endpoints работают только по TLS в production.
- Auth endpoints имеют rate limits.
- Cabinet API проверяет RBAC.
- Каждый запрос имеет tenant scope.
- Ingest API проверяет HMAC, timestamp и replay.
- JSON payload валидируется по schema.
- Secrets маскируются в логах.
- Notification settings хранятся encrypted.
- Audit log пишется для ключей, пользователей и notification channels.

## Bitrix Module

- Forms защищены `bitrix_sessid_post()` и `check_bitrix_sessid()`.
- Доступ к настройкам ограничен правами модуля.
- HTML output экранируется через `htmlspecialcharsbx`.
- JSON кодируется через `Bitrix\Main\Web\Json`.
- API secret хранится encrypted.
- Raw payload не логируется по умолчанию.
- Collector не читает произвольные пользовательские пути.
- Shell commands не выполняются.
- Тяжёлые операции имеют лимиты времени.

## Probe And Webhook

- URL проходит SSRF validation.
- Private IP запрещены по умолчанию.
- Redirect target валидируется повторно.
- Timeout задан для DNS/connect/total request.
- Webhook retries не бесконечны.
- Response body не логируется по умолчанию.

## Data Privacy

- Нет сбора персональных данных по умолчанию.
- Нет хранения дампов БД и файлов.
- Есть страница состава передаваемых данных.
- Retention policy применима по тарифу.

## Release Gate

Публичный MVP не выпускается, если есть открытые high severity security findings по:

- signature bypass;
- tenant isolation;
- secret leakage;
- SSRF;
- arbitrary file read;
- remote command execution.

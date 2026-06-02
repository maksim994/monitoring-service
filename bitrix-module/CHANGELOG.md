# Changelog

## 0.1.0 — 2026-06-02

### Added

- Подключение к Monitoring Service SaaS через Site ID и API secret.
- Отправка signed heartbeat и metrics batch с HMAC-SHA256.
- Локальная очередь с retry при недоступности API.
- Collectors: environment и disk.
- Агент cron для периодической отправки данных.
- Страница настроек в админке Bitrix.
- Тестовая отправка heartbeat из админки.

### Security

- Проверка `bitrix_sessid` на форме настроек.
- Доступ только для администраторов Bitrix.
- Экранирование вывода через `htmlspecialcharsbx`.
- API secret маскируется в форме; новое значение сохраняется только при явном вводе.

### Known limitations

- Collectors backup/modules/agents — в roadmap post-MVP.
- Шифрование secret в Option пока не включено (хранится в module options).
- Поддерживается PHP 8.2+ и Bitrix Main module.

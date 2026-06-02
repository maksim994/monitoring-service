# Bitrix Marketplace Submission

## Цель

Подготовить модуль `vendor.monitoring` к публикации или к ручной поставке клиентам архивом.

## До Публикации

- Выбрать финальный vendor/module id.
- Проверить установку на чистом Bitrix.
- Проверить удаление.
- Подготовить русские lang-файлы.
- Подготовить описание модуля.
- Подготовить скриншоты админки.
- Описать состав передаваемых данных.
- Указать совместимые редакции и версии PHP.
- Добавить changelog.
- Проверить отсутствие правок ядра.

## Security Checklist

- Forms use session checks.
- Settings access is permission-protected.
- Secrets are encrypted and masked.
- No personal data is collected by default.
- No arbitrary shell commands.
- No arbitrary file reads from user input.
- Heavy scans have limits.

## Manual Distribution

Beta should not depend on Marketplace approval. Maintain archive installation flow:

```text
/local/modules/vendor.monitoring/
```

Include:

- installation guide;
- transmitted data document;
- troubleshooting;
- changelog.

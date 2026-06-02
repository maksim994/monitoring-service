# Notifications Guide

## Каналы

MVP поддерживает:

- Telegram;
- email;
- webhook.

## Telegram

1. Создайте или выберите bot token.
2. Укажите chat id.
3. Сохраните канал.
4. Отправьте тестовое уведомление.

## Email

1. Укажите email получателя.
2. Сохраните канал.
3. Отправьте тестовое уведомление.

## Webhook

1. Укажите HTTPS URL.
2. При необходимости задайте secret для подписи.
3. Сохраните канал.
4. Отправьте тестовое уведомление.

## Severity Routing

Для каждого канала можно выбрать:

- warning;
- critical;
- конкретные проверки.

Critical notifications can be repeated by interval until the incident is resolved or muted.

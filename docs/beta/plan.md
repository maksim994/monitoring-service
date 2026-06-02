# Closed Beta Plan

## Цель

Проверить Monitoring Service на реальных Bitrix-проектах и подтвердить, что модуль устанавливается, данные собираются без заметной нагрузки, инциденты полезны, а уведомления доходят вовремя.

## Длительность

Минимум 3-4 недели.

## Состав Beta

Подключить 5-10 сайтов:

- 2 свежих Bitrix-проекта;
- 2 legacy-проекта;
- 1 интернет-магазин;
- 1 коробочный Битрикс24;
- 1 shared-hosting проект;
- 1 проект с большим `/upload/`;
- 1 проект с agents на cron;
- 1 проект с agents на hit-режиме.

## Что Измерять

| Metric | Target |
| --- | --- |
| Time to install | <= 15 minutes |
| Time to first heartbeat | <= 2 minutes |
| Heartbeat stability | >= 80% beta sites stable |
| False critical incidents | < 2-5% |
| Telegram/email delivery | <= 60 seconds for critical |
| Module load impact | No noticeable degradation |
| Collector failures | Tracked by collector type |
| User understanding | Users can explain incident action |

## Beta Workflow

1. Подготовить список сайтов и владельцев.
2. Провести установку модуля вместе с владельцем.
3. Зафиксировать время установки.
4. Включить базовые проверки.
5. Настроить Telegram/email.
6. Наблюдать 3-4 недели.
7. Разобрать ложные срабатывания.
8. Оптимизировать collectors и thresholds.
9. Подготовить release notes публичного MVP.

## Обратная Связь

Собирать:

- какие проверки считают ценными;
- какие уведомления мешают;
- какие рекомендации непонятны;
- где установка ломалась;
- какие Bitrix-версии и хостинги проблемные.

## Критерии Перехода К Публичному MVP

- 80% beta-сайтов стабильно присылают heartbeat.
- Нет критичных ошибок установки/удаления модуля.
- Ложные critical-инциденты ниже 2-5%.
- Telegram/email delivery стабильно работает.
- Модуль не создаёт заметной нагрузки.
- Есть документация установки и troubleshooting.
- Поддержка понимает типовые ошибки.
- Есть публичное описание состава передаваемых данных.
- Security review не содержит high severity blockers.

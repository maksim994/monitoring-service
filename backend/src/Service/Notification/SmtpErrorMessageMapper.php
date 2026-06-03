<?php

declare(strict_types=1);

namespace App\Service\Notification;

final class SmtpErrorMessageMapper
{
    public static function toUserMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (
            str_contains($message, 'parol prilozheniya')
            || str_contains($message, 'Application password is REQUIRED')
        ) {
            return 'Mail.ru: для SMTP нужен пароль приложения, а не пароль входа в почту. '
                .'Создайте его в настройках ящика и подставьте в MAILER_DSN на сервере: '
                .'https://help.mail.ru/mail/security/protection/external';
        }

        if (str_contains($message, 'not local sender over smtp')) {
            return 'SMTP: адрес отправителя (MAILER_FROM) должен совпадать с логином в MAILER_DSN, '
                .'например mail@mv-deploy.ru.';
        }

        if (str_contains($message, 'Failed to authenticate on SMTP server')) {
            return 'Не удалось войти на SMTP-сервер. Проверьте логин и пароль в MAILER_DSN '
                .'(для Mail.ru — пароль приложения).';
        }

        return $message;
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Notification;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mail.ru and similar providers reject mail when From ≠ SMTP login (550 not local sender).
 */
final class MailerFromResolver
{
    private const DEFAULT_PLACEHOLDER = 'monitoring@localhost';

    public function __construct(
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
    ) {
    }

    public function resolve(): string
    {
        $configured = trim($this->mailerFrom);
        if ($this->isExplicitFrom($configured)) {
            return $configured;
        }

        $fromDsn = $this->extractUserFromDsn($this->mailerDsn);
        if ($fromDsn !== null) {
            return $fromDsn;
        }

        if ($configured !== '') {
            return $configured;
        }

        throw new \RuntimeException(
            'Задайте MAILER_FROM на сервере — тот же адрес, что логин в MAILER_DSN (например mail@mv-deploy.ru).',
        );
    }

    private function isExplicitFrom(string $from): bool
    {
        if ($from === '' || $from === self::DEFAULT_PLACEHOLDER) {
            return false;
        }

        return str_contains($from, '@') && !str_contains($from, 'localhost');
    }

    private function extractUserFromDsn(string $dsn): ?string
    {
        if ($dsn === '' || str_starts_with($dsn, 'null://')) {
            return null;
        }

        $parsed = parse_url($dsn);
        $user = $parsed['user'] ?? null;
        if (!is_string($user) || $user === '') {
            return null;
        }

        $email = rawurldecode($user);

        return str_contains($email, '@') ? $email : null;
    }
}

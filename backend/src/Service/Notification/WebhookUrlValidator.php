<?php

declare(strict_types=1);

namespace App\Service\Notification;

final class WebhookUrlValidator
{
    public function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Webhook URL must use http or https.');
        }

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new \InvalidArgumentException('Webhook URL host is not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);

            return;
        }

        $ips = gethostbynamel($host);
        if ($ips === false || $ips === []) {
            throw new \InvalidArgumentException('Webhook URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            $this->assertPublicIp($ip);
        }
    }

    private function assertPublicIp(string $ip): void
    {
        if (!$this->isPublicIp($ip)) {
            throw new \InvalidArgumentException('Webhook URL must not target private networks.');
        }
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}

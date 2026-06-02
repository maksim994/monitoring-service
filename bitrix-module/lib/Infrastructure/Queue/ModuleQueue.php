<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Infrastructure\Queue;

use Bitrix\Main\Config\Option;

final class ModuleQueue
{
    private const MODULE_ID = 'vendor.monitoring';
    private const OPTION_KEY = 'outbox_queue';

    /** @param array<string, mixed> $payload */
    public function enqueue(string $type, array $payload): void
    {
        $queue = $this->load();
        $queue[] = [
            'id' => bin2hex(random_bytes(8)),
            'type' => $type,
            'payload' => $payload,
            'attempts' => 0,
            'nextAttemptAt' => time(),
            'createdAt' => time(),
        ];
        $this->save($queue);
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        $now = time();

        return array_values(array_filter($this->load(), static fn (array $item): bool => ($item['nextAttemptAt'] ?? 0) <= $now));
    }

    public function markSent(string $id): void
    {
        $queue = array_values(array_filter($this->load(), static fn (array $item): bool => ($item['id'] ?? '') !== $id));
        $this->save($queue);
    }

    public function markFailed(string $id, string $error): void
    {
        $queue = $this->load();
        foreach ($queue as &$item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            $attempts = (int) ($item['attempts'] ?? 0) + 1;
            $item['attempts'] = $attempts;
            $item['lastError'] = $error;
            $item['nextAttemptAt'] = time() + min(3600, 30 * (2 ** min($attempts, 5)));
        }
        unset($item);
        $this->save($queue);
    }

    /** @return list<array<string, mixed>> */
    private function load(): array
    {
        $raw = Option::get(self::MODULE_ID, self::OPTION_KEY, '[]');
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param list<array<string, mixed>> $queue */
    private function save(array $queue): void
    {
        Option::set(self::MODULE_ID, self::OPTION_KEY, json_encode(array_values($queue), JSON_UNESCAPED_UNICODE));
    }
}

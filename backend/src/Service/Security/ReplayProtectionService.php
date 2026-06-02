<?php

declare(strict_types=1);

namespace App\Service\Security;

use Psr\Cache\CacheItemPoolInterface;

final class ReplayProtectionService
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $ttlSeconds = 600,
    ) {
    }

    public function assertNotReplayed(string $siteId, string $requestId): void
    {
        $cacheKey = sprintf('module_request_%s_%s', $siteId, $requestId);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            throw new ModuleAuthException('signature_replay_detected', 'Request id has already been used.');
        }

        $item->set(true);
        $item->expiresAfter($this->ttlSeconds);
        $this->cache->save($item);
    }
}

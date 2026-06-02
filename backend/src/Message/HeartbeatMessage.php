<?php

declare(strict_types=1);

namespace App\Message;

final class HeartbeatMessage
{
    public function __construct(
        public readonly string $siteId,
        public readonly string $requestId,
        public readonly array $payload,
    ) {
    }
}

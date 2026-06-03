<?php

declare(strict_types=1);

namespace App\Service\Probe;

final readonly class DomainExpiryLookupResult
{
    public function __construct(
        public ?\DateTimeImmutable $expiresAt,
        public ?int $daysLeft,
        public ?string $error,
        public string $source,
    ) {
    }

    public static function failed(string $error): self
    {
        return new self(null, null, $error, 'unknown');
    }

    public function isSuccess(): bool
    {
        return $this->expiresAt !== null && $this->daysLeft !== null;
    }
}

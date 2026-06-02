<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: 'plans')]
class Plan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column(name: 'max_sites')]
    private int $maxSites;

    #[ORM\Column(name: 'max_users')]
    private int $maxUsers;

    #[ORM\Column(name: 'min_uptime_interval_seconds')]
    private int $minUptimeIntervalSeconds;

    #[ORM\Column(name: 'webhooks_enabled')]
    private bool $webhooksEnabled = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(name: 'sort_order')]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $code,
        string $label,
        int $maxSites,
        int $maxUsers,
        int $minUptimeIntervalSeconds,
        bool $webhooksEnabled,
        int $sortOrder = 0,
    ) {
        $this->id = Uuid::v7();
        $this->code = $code;
        $this->label = $label;
        $this->maxSites = $maxSites;
        $this->maxUsers = $maxUsers;
        $this->minUptimeIntervalSeconds = $minUptimeIntervalSeconds;
        $this->webhooksEnabled = $webhooksEnabled;
        $this->sortOrder = $sortOrder;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;
        $this->touch();

        return $this;
    }

    public function getMaxSites(): int
    {
        return $this->maxSites;
    }

    public function setMaxSites(int $maxSites): self
    {
        $this->maxSites = $maxSites;
        $this->touch();

        return $this;
    }

    public function getMaxUsers(): int
    {
        return $this->maxUsers;
    }

    public function setMaxUsers(int $maxUsers): self
    {
        $this->maxUsers = $maxUsers;
        $this->touch();

        return $this;
    }

    public function getMinUptimeIntervalSeconds(): int
    {
        return $this->minUptimeIntervalSeconds;
    }

    public function setMinUptimeIntervalSeconds(int $minUptimeIntervalSeconds): self
    {
        $this->minUptimeIntervalSeconds = $minUptimeIntervalSeconds;
        $this->touch();

        return $this;
    }

    public function isWebhooksEnabled(): bool
    {
        return $this->webhooksEnabled;
    }

    public function setWebhooksEnabled(bool $webhooksEnabled): self
    {
        $this->webhooksEnabled = $webhooksEnabled;
        $this->touch();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        $this->touch();

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        $this->touch();

        return $this;
    }

    /** @return array{maxSites: int, minUptimeIntervalSeconds: int, maxUsers: int, webhooksEnabled: bool, label: string} */
    public function toConfigArray(): array
    {
        return [
            'maxSites' => $this->maxSites,
            'minUptimeIntervalSeconds' => $this->minUptimeIntervalSeconds,
            'maxUsers' => $this->maxUsers,
            'webhooksEnabled' => $this->webhooksEnabled,
            'label' => $this->label,
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

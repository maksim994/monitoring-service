<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CheckRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CheckRepository::class)]
#[ORM\Table(name: 'checks')]
class Check
{
    public const TYPE_UPTIME_HTTP = 'uptime_http';
    public const TYPE_SSL_EXPIRY = 'ssl_expiry';
    public const TYPE_DOMAIN_EXPIRY = 'domain_expiry';
    public const TYPE_DISK_LOW = 'disk_low';
    public const TYPE_BACKUP_STALE = 'backup_stale';
    public const TYPE_AGENTS_LAG = 'agents_lag';
    public const TYPE_MODULES_UPDATES = 'modules_updates';
    public const TYPE_HEARTBEAT_MISSING = 'heartbeat_missing';
    public const TYPE_BITRIX_LICENSE_EXPIRY = 'bitrix_license_expiry';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 80)]
    private string $type;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(name: 'interval_seconds')]
    private int $intervalSeconds;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'settings_json', type: 'json')]
    private array $settingsJson = [];

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'last_status', length: 30, nullable: true)]
    private ?string $lastStatus = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'last_value_json', type: 'json', nullable: true)]
    private ?array $lastValueJson = null;

    #[ORM\Column(name: 'last_collected_at', nullable: true)]
    private ?\DateTimeImmutable $lastCollectedAt = null;

    public function __construct(
        Organization $organization,
        Site $site,
        string $type,
        int $intervalSeconds,
        array $settingsJson = [],
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->site = $site;
        $this->type = $type;
        $this->intervalSeconds = $intervalSeconds;
        $this->settingsJson = $settingsJson;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function areNotificationsEnabled(): bool
    {
        return ($this->settingsJson['notificationsEnabled'] ?? true) !== false;
    }

    public function setNotificationsEnabled(bool $enabled): self
    {
        $this->settingsJson['notificationsEnabled'] = $enabled;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getIntervalSeconds(): int
    {
        return $this->intervalSeconds;
    }

    /** @return array<string, mixed> */
    public function getSettingsJson(): array
    {
        return $this->settingsJson;
    }

    public function getTargetUrl(): ?string
    {
        $url = $this->settingsJson['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** @param array<string, mixed> $settingsJson */
    public function replaceSettingsJson(array $settingsJson): self
    {
        $this->settingsJson = $settingsJson;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getLastStatus(): ?string
    {
        return $this->lastStatus;
    }

    /** @return array<string, mixed>|null */
    public function getLastValueJson(): ?array
    {
        return $this->lastValueJson;
    }

    public function getLastCollectedAt(): ?\DateTimeImmutable
    {
        return $this->lastCollectedAt;
    }

    /** @param array<string, mixed> $value */
    public function recordSnapshot(string $status, array $value, ?\DateTimeImmutable $collectedAt = null): self
    {
        $this->lastStatus = $status;
        $this->lastValueJson = $value;
        $this->lastCollectedAt = $collectedAt ?? new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}

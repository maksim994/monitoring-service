<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SiteRepository::class)]
#[ORM\Table(name: 'sites')]
class Site
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';
    public const STATUS_DISABLED = 'disabled';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'sites')]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 255)]
    private string $domain;

    #[ORM\Column(name: 'site_url', type: 'text')]
    private string $siteUrl;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'module_version', length: 50, nullable: true)]
    private ?string $moduleVersion = null;

    #[ORM\Column(name: 'bitrix_version', length: 50, nullable: true)]
    private ?string $bitrixVersion = null;

    #[ORM\Column(name: 'php_version', length: 50, nullable: true)]
    private ?string $phpVersion = null;

    #[ORM\Column(name: 'last_heartbeat_at', nullable: true)]
    private ?\DateTimeImmutable $lastHeartbeatAt = null;

    #[ORM\Column(name: 'config_version')]
    private int $configVersion = 1;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Organization $organization, string $domain, string $siteUrl)
    {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->domain = $domain;
        $this->siteUrl = $siteUrl;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getSiteUrl(): string
    {
        return $this->siteUrl;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function getModuleVersion(): ?string
    {
        return $this->moduleVersion;
    }

    public function setModuleVersion(?string $moduleVersion): self
    {
        $this->moduleVersion = $moduleVersion;
        $this->touch();

        return $this;
    }

    public function getBitrixVersion(): ?string
    {
        return $this->bitrixVersion;
    }

    public function setBitrixVersion(?string $bitrixVersion): self
    {
        $this->bitrixVersion = $bitrixVersion;
        $this->touch();

        return $this;
    }

    public function getPhpVersion(): ?string
    {
        return $this->phpVersion;
    }

    public function setPhpVersion(?string $phpVersion): self
    {
        $this->phpVersion = $phpVersion;
        $this->touch();

        return $this;
    }

    public function getLastHeartbeatAt(): ?\DateTimeImmutable
    {
        return $this->lastHeartbeatAt;
    }

    public function recordHeartbeat(?string $moduleVersion, ?string $bitrixVersion, ?string $phpVersion): self
    {
        $this->lastHeartbeatAt = new \DateTimeImmutable();
        $this->moduleVersion = $moduleVersion ?? $this->moduleVersion;
        $this->bitrixVersion = $bitrixVersion ?? $this->bitrixVersion;
        $this->phpVersion = $phpVersion ?? $this->phpVersion;

        if ($this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_OK;
        }

        $this->touch();

        return $this;
    }

    public function getConfigVersion(): int
    {
        return $this->configVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

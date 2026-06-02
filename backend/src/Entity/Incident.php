<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IncidentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Table(name: 'incidents')]
class Incident
{
    public const CHECK_HEARTBEAT_MISSING = 'heartbeat_missing';
    public const CHECK_UPTIME_HTTP = 'uptime_http';
    public const CHECK_SSL_EXPIRY = 'ssl_expiry';
    public const CHECK_DISK_LOW = 'disk_low';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_MUTED = 'muted';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(name: 'check_type', length: 80)]
    private string $checkType;

    #[ORM\Column(length: 255)]
    private string $fingerprint;

    #[ORM\Column(length: 20)]
    private string $severity;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(name: 'opened_at')]
    private \DateTimeImmutable $openedAt;

    #[ORM\Column(name: 'acknowledged_at', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(name: 'resolved_at', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(name: 'muted_until', nullable: true)]
    private ?\DateTimeImmutable $mutedUntil = null;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'last_evidence_json', type: 'json')]
    private array $lastEvidenceJson = [];

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Organization $organization,
        Site $site,
        string $checkType,
        string $fingerprint,
        string $severity,
        string $title,
        array $evidence = [],
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->site = $site;
        $this->checkType = $checkType;
        $this->fingerprint = $fingerprint;
        $this->severity = $severity;
        $this->title = $title;
        $this->lastEvidenceJson = $evidence;
        $now = new \DateTimeImmutable();
        $this->openedAt = $now;
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

    public function getCheckType(): string
    {
        return $this->checkType;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        $this->touch();

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_ACKNOWLEDGED], true);
    }

    public function acknowledge(): self
    {
        $this->status = self::STATUS_ACKNOWLEDGED;
        $this->acknowledgedAt = new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function resolve(): self
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolvedAt = new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->touch();

        return $this;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /** @return array<string, mixed> */
    public function getLastEvidenceJson(): array
    {
        return $this->lastEvidenceJson;
    }

    /** @param array<string, mixed> $evidence */
    public function updateEvidence(array $evidence): self
    {
        $this->lastEvidenceJson = $evidence;
        $this->touch();

        return $this;
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

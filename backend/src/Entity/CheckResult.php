<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CheckResultRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CheckResultRepository::class)]
#[ORM\Table(name: 'check_results')]
class CheckResult
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';
    public const STATUS_UNKNOWN = 'unknown';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Check::class)]
    #[ORM\JoinColumn(name: 'check_id', nullable: false, onDelete: 'CASCADE')]
    private Check $check;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 30)]
    private string $status;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'value_json', type: 'json')]
    private array $valueJson = [];

    #[ORM\Column(name: 'consecutive_failures')]
    private int $consecutiveFailures = 0;

    #[ORM\Column(name: 'probe_id', length: 64, nullable: true)]
    private ?string $probeId = null;

    #[ORM\Column(name: 'checked_at')]
    private \DateTimeImmutable $checkedAt;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Check $check, string $status, array $valueJson, ?string $probeId = null, int $consecutiveFailures = 0)
    {
        $this->id = Uuid::v7();
        $this->check = $check;
        $this->site = $check->getSite();
        $this->status = $status;
        $this->valueJson = $valueJson;
        $this->probeId = $probeId;
        $now = new \DateTimeImmutable();
        $this->checkedAt = $now;
        $this->createdAt = $now;
        $this->consecutiveFailures = $consecutiveFailures > 0
            ? $consecutiveFailures
            : ($status === self::STATUS_OK ? 0 : 1);
    }

    public static function fromProbe(Check $check, string $status, array $valueJson, ?self $previous, ?string $probeId): self
    {
        $consecutiveFailures = 0;
        if ($status === self::STATUS_OK) {
            $consecutiveFailures = 0;
        } elseif ($previous !== null && $previous->getStatus() !== self::STATUS_OK) {
            $consecutiveFailures = $previous->getConsecutiveFailures() + 1;
        } else {
            $consecutiveFailures = 1;
        }

        $valueJson['consecutiveFailures'] = $consecutiveFailures;

        return new self($check, $status, $valueJson, $probeId, $consecutiveFailures);
    }

    public function getCheck(): Check
    {
        return $this->check;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function getValueJson(): array
    {
        return $this->valueJson;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function getProbeId(): ?string
    {
        return $this->probeId;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
}

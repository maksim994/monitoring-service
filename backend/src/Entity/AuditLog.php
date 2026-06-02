<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(name: 'actor_user_id', type: 'uuid', nullable: true)]
    private ?Uuid $actorUserId;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(name: 'target_type', length: 80)]
    private string $targetType;

    #[ORM\Column(name: 'target_id', length: 255, nullable: true)]
    private ?string $targetId;

    #[ORM\Column(type: 'text')]
    private string $message;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'payload_json', type: 'json')]
    private array $payloadJson = [];

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Organization $organization,
        ?Uuid $actorUserId,
        string $action,
        string $targetType,
        ?string $targetId,
        string $message,
        array $payload = [],
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->actorUserId = $actorUserId;
        $this->action = $action;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->message = $message;
        $this->payloadJson = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getActorUserId(): ?Uuid
    {
        return $this->actorUserId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /** @return array<string, mixed> */
    public function getPayloadJson(): array
    {
        return $this->payloadJson;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

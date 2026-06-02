<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'incident_events')]
class IncidentEvent
{
    public const TYPE_OPENED = 'opened';
    public const TYPE_EVIDENCE_UPDATED = 'evidence_updated';
    public const TYPE_ACKNOWLEDGED = 'acknowledged';
    public const TYPE_RESOLVED = 'resolved';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Incident::class)]
    #[ORM\JoinColumn(name: 'incident_id', nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    #[ORM\Column(length: 80)]
    private string $type;

    #[ORM\Column(type: 'text')]
    private string $message;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'payload_json', type: 'json')]
    private array $payloadJson = [];

    #[ORM\Column(name: 'created_by', type: 'uuid', nullable: true)]
    private ?Uuid $createdBy = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Incident $incident, string $type, string $message, array $payload = [], ?Uuid $createdBy = null)
    {
        $this->id = Uuid::v7();
        $this->incident = $incident;
        $this->type = $type;
        $this->message = $message;
        $this->payloadJson = $payload;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getType(): string
    {
        return $this->type;
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

    public function getCreatedBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

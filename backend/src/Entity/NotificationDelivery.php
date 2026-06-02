<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationDeliveryRepository::class)]
#[ORM\Table(name: 'notification_deliveries')]
class NotificationDelivery
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: NotificationChannel::class)]
    #[ORM\JoinColumn(name: 'channel_id', nullable: false, onDelete: 'CASCADE')]
    private NotificationChannel $channel;

    #[ORM\Column(name: 'incident_id', type: 'uuid', nullable: true)]
    private ?Uuid $incidentId;

    #[ORM\Column(length: 30)]
    private string $status;

    #[ORM\Column]
    private int $attempt = 1;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(name: 'sent_at', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Organization $organization,
        NotificationChannel $channel,
        string $status,
        ?Uuid $incidentId = null,
        ?string $error = null,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->channel = $channel;
        $this->status = $status;
        $this->incidentId = $incidentId;
        $this->error = $error;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        if ($status === self::STATUS_SENT) {
            $this->sentAt = $now;
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getChannel(): NotificationChannel
    {
        return $this->channel;
    }

    public function getIncidentId(): ?Uuid
    {
        return $this->incidentId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

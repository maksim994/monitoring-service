<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MaintenanceWindowRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MaintenanceWindowRepository::class)]
#[ORM\Table(name: 'maintenance_windows')]
class MaintenanceWindow
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(name: 'check_type', length: 80, nullable: true)]
    private ?string $checkType;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(name: 'starts_at')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(name: 'cancelled_at', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(name: 'created_by', type: 'uuid', nullable: true)]
    private ?Uuid $createdBy;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Organization $organization,
        Site $site,
        string $title,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        ?string $checkType = null,
        ?Uuid $createdBy = null,
    ) {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->site = $site;
        $this->title = $title;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->checkType = $checkType !== '' ? $checkType : null;
        $this->createdBy = $createdBy;
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

    public function getCheckType(): ?string
    {
        return $this->checkType;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancel(): self
    {
        $this->cancelledAt = new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function isActive(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return !$this->isCancelled()
            && $this->startsAt <= $now
            && $this->endsAt > $now;
    }

    public function isScheduled(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return !$this->isCancelled() && $this->startsAt > $now;
    }

    public function coversCheckType(string $checkType): bool
    {
        return $this->checkType === null || $this->checkType === $checkType;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

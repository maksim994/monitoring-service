<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SiteKeyRepository::class)]
#[ORM\Table(name: 'site_keys')]
class SiteKey
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(name: 'site_id', nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(name: 'secret_encrypted', type: 'text')]
    private string $secretEncrypted;

    #[ORM\Column(name: 'active_from')]
    private \DateTimeImmutable $activeFrom;

    #[ORM\Column(name: 'active_to', nullable: true)]
    private ?\DateTimeImmutable $activeTo = null;

    #[ORM\Column(name: 'revoked_at', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Site $site, string $secretEncrypted)
    {
        $this->id = Uuid::v7();
        $this->site = $site;
        $this->secretEncrypted = $secretEncrypted;
        $this->activeFrom = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getSecretEncrypted(): string
    {
        return $this->secretEncrypted;
    }

    public function isActive(): bool
    {
        if ($this->revokedAt !== null) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if ($this->activeTo !== null && $now > $this->activeTo) {
            return false;
        }

        return $now >= $this->activeFrom;
    }

    public function revoke(): self
    {
        $this->revokedAt = new \DateTimeImmutable();

        return $this;
    }
}

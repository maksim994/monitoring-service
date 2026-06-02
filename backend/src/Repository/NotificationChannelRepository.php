<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationChannel;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationChannel> */
class NotificationChannelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationChannel::class);
    }

    /** @return list<NotificationChannel> */
    public function findEnabledByOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization, 'enabled' => true], ['createdAt' => 'ASC']);
    }

    /** @return list<NotificationChannel> */
    public function findByOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['createdAt' => 'ASC']);
    }
}

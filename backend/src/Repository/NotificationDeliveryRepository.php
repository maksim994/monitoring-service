<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationDelivery;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationDelivery> */
class NotificationDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDelivery::class);
    }

    /** @return list<NotificationDelivery> */
    public function findByOrganization(Organization $organization, int $limit = 50): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationChannel;
use App\Entity\NotificationDelivery;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<NotificationDelivery> */
class NotificationDeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDelivery::class);
    }

    public function findLastSuccessfulSentAt(Uuid $incidentId, NotificationChannel $channel): ?\DateTimeImmutable
    {
        $delivery = $this->createQueryBuilder('d')
            ->andWhere('d.incidentId = :incidentId')
            ->andWhere('d.channel = :channel')
            ->andWhere('d.status = :status')
            ->andWhere('d.sentAt IS NOT NULL')
            ->setParameter('incidentId', $incidentId)
            ->setParameter('channel', $channel)
            ->setParameter('status', NotificationDelivery::STATUS_SENT)
            ->orderBy('d.sentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $delivery instanceof NotificationDelivery ? $delivery->getSentAt() : null;
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

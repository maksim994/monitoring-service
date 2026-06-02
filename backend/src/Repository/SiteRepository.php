<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Site> */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    /** @return list<Site> */
    public function findByOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['createdAt' => 'DESC']);
    }

    public function countActiveByOrganization(Organization $organization): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.organization = :organization')
            ->andWhere('s.status != :disabled')
            ->setParameter('organization', $organization)
            ->setParameter('disabled', Site::STATUS_DISABLED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Site> */
    public function findAllActiveForAlerts(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status != :disabled')
            ->setParameter('disabled', Site::STATUS_DISABLED)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Site> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.status != :disabled')
            ->setParameter('disabled', Site::STATUS_DISABLED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Check;
use App\Entity\Organization;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Check> */
class CheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Check::class);
    }

    /** @return list<Check> */
    public function findEnabledForProbes(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.enabled = true')
            ->andWhere('c.type IN (:types)')
            ->setParameter('types', [
                Check::TYPE_UPTIME_HTTP,
                Check::TYPE_SSL_EXPIRY,
                Check::TYPE_DOMAIN_EXPIRY,
            ])
            ->getQuery()
            ->getResult();
    }

    /** @return list<Check> */
    public function findBySite(Site $site): array
    {
        return $this->findBy(['site' => $site], ['type' => 'ASC']);
    }

    /** @return list<Check> */
    public function findEnabledProbesForSite(Site $site): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.site = :site')
            ->andWhere('c.enabled = true')
            ->andWhere('c.type IN (:types)')
            ->setParameter('site', $site)
            ->setParameter('types', [
                Check::TYPE_UPTIME_HTTP,
                Check::TYPE_SSL_EXPIRY,
                Check::TYPE_DOMAIN_EXPIRY,
            ])
            ->orderBy('c.type', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySiteAndType(Site $site, string $type): ?Check
    {
        return $this->findOneBy(['site' => $site, 'type' => $type]);
    }
}

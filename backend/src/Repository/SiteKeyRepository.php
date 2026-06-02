<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Site;
use App\Entity\SiteKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SiteKey> */
class SiteKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteKey::class);
    }

    public function findActiveKeyForSite(Site $site): ?SiteKey
    {
        return $this->createQueryBuilder('sk')
            ->andWhere('sk.site = :site')
            ->andWhere('sk.revokedAt IS NULL')
            ->setParameter('site', $site)
            ->orderBy('sk.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

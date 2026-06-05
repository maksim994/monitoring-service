<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Incident;
use App\Entity\Organization;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Incident> */
class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    public function findActiveBySiteAndCheckType(Site $site, string $checkType, string $fingerprint): ?Incident
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.site = :site')
            ->andWhere('i.checkType = :checkType')
            ->andWhere('i.fingerprint = :fingerprint')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('site', $site)
            ->setParameter('checkType', $checkType)
            ->setParameter('fingerprint', $fingerprint)
            ->setParameter('statuses', [Incident::STATUS_OPEN, Incident::STATUS_ACKNOWLEDGED])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Incident> */
    public function findActiveBySiteAndCheckTypeAll(Site $site, string $checkType): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.site = :site')
            ->andWhere('i.checkType = :checkType')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('site', $site)
            ->setParameter('checkType', $checkType)
            ->setParameter('statuses', [Incident::STATUS_OPEN, Incident::STATUS_ACKNOWLEDGED])
            ->getQuery()
            ->getResult();
    }

    /** @return list<Incident> */
    public function findByOrganization(Organization $organization, ?string $status = null, ?Site $site = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('i.openedAt', 'DESC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        if ($site instanceof Site) {
            $qb->andWhere('i.site = :site')
                ->setParameter('site', $site);
        }

        return $qb->getQuery()->getResult();
    }

    public function countOpenBySite(Site $site): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.site = :site')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('site', $site)
            ->setParameter('statuses', [Incident::STATUS_OPEN, Incident::STATUS_ACKNOWLEDGED])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOpenByOrganization(Organization $organization): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.organization = :organization')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('organization', $organization)
            ->setParameter('statuses', [Incident::STATUS_OPEN, Incident::STATUS_ACKNOWLEDGED])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Incident> */
    public function findActiveCritical(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.severity = :severity')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('severity', Incident::SEVERITY_CRITICAL)
            ->setParameter('statuses', [Incident::STATUS_OPEN, Incident::STATUS_ACKNOWLEDGED])
            ->orderBy('i.openedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Incident> */
    public function findActiveBySite(Site $site): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.site = :site')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('site', $site)
            ->setParameter('statuses', [Incident::STATUS_OPEN, Incident::STATUS_ACKNOWLEDGED])
            ->orderBy('i.severity', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

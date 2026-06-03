<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MaintenanceWindow;
use App\Entity\Site;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MaintenanceWindow> */
class MaintenanceWindowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MaintenanceWindow::class);
    }

    public function isSuppressed(Site $site, string $checkType, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        $count = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.site = :site')
            ->andWhere('m.cancelledAt IS NULL')
            ->andWhere('m.startsAt <= :now')
            ->andWhere('m.endsAt > :now')
            ->andWhere('m.checkType IS NULL OR m.checkType = :checkType')
            ->setParameter('site', $site)
            ->setParameter('now', $now)
            ->setParameter('checkType', $checkType)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /** @return list<MaintenanceWindow> */
    public function findVisibleBySite(Site $site): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->andWhere('m.site = :site')
            ->andWhere('m.cancelledAt IS NULL')
            ->andWhere('m.endsAt > :now')
            ->setParameter('site', $site)
            ->setParameter('now', $now)
            ->orderBy('m.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Check;
use App\Entity\CheckResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CheckResult> */
class CheckResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CheckResult::class);
    }

    public function findLatestForCheck(Check $check): ?CheckResult
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.check = :check')
            ->setParameter('check', $check)
            ->orderBy('r.checkedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

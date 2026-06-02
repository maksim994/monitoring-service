<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OrganizationUser> */
class OrganizationUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationUser::class);
    }

    public function findPrimaryOrganizationForUser(string $userId): ?OrganizationUser
    {
        return $this->createQueryBuilder('ou')
            ->andWhere('ou.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ou.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByOrganization(Organization $organization): int
    {
        return (int) $this->createQueryBuilder('ou')
            ->select('COUNT(ou.user)')
            ->andWhere('ou.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<OrganizationUser> */
    public function findAllByOrganization(Organization $organization): array
    {
        return $this->findBy(['organization' => $organization], ['createdAt' => 'ASC']);
    }

    public function findMembership(Organization $organization, string $userId): ?OrganizationUser
    {
        return $this->findOneBy([
            'organization' => $organization,
            'user' => $userId,
        ]);
    }
}

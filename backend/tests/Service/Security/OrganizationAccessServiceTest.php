<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\User;
use App\Repository\OrganizationUserRepository;
use App\Service\Security\AccessDeniedException;
use App\Service\Security\OrganizationAccessService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrganizationAccessServiceTest extends TestCase
{
    #[DataProvider('rolePermissionsProvider')]
    public function testRolePermissions(string $role, string $permission, bool $allowed): void
    {
        $user = new User('user@example.com', 'hash', 'User');
        $organization = new Organization('Org');
        $membership = new OrganizationUser($organization, $user, $role);

        $repository = $this->createMock(OrganizationUserRepository::class);
        $repository->method('findPrimaryOrganizationForUser')->willReturn($membership);

        $service = new OrganizationAccessService($repository);

        self::assertSame($allowed, $service->can($user, $permission));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function rolePermissionsProvider(): iterable
    {
        yield 'owner manage users' => ['owner', OrganizationAccessService::PERM_MANAGE_USERS, true];
        yield 'admin manage users' => ['admin', OrganizationAccessService::PERM_MANAGE_USERS, true];
        yield 'viewer manage users' => ['viewer', OrganizationAccessService::PERM_MANAGE_USERS, false];
        yield 'integrator manage sites' => ['integrator', OrganizationAccessService::PERM_MANAGE_SITES, true];
        yield 'operator acknowledge incidents' => ['operator', OrganizationAccessService::PERM_ACKNOWLEDGE_INCIDENTS, true];
        yield 'viewer acknowledge incidents' => ['viewer', OrganizationAccessService::PERM_ACKNOWLEDGE_INCIDENTS, false];
        yield 'owner manage plan' => ['owner', OrganizationAccessService::PERM_MANAGE_PLAN, true];
        yield 'admin manage plan' => ['admin', OrganizationAccessService::PERM_MANAGE_PLAN, false];
    }

    public function testAssertCanThrowsForDeniedPermission(): void
    {
        $user = new User('viewer@example.com', 'hash', 'Viewer');
        $organization = new Organization('Org');
        $membership = new OrganizationUser($organization, $user, OrganizationUser::ROLE_VIEWER);

        $repository = $this->createMock(OrganizationUserRepository::class);
        $repository->method('findPrimaryOrganizationForUser')->willReturn($membership);

        $service = new OrganizationAccessService($repository);

        $this->expectException(AccessDeniedException::class);
        $service->assertCan($user, OrganizationAccessService::PERM_MANAGE_SITES);
    }
}

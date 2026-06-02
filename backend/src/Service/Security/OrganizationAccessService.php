<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\OrganizationUser;
use App\Entity\User;
use App\Repository\OrganizationUserRepository;

final class OrganizationAccessService
{
    public const PERM_MANAGE_SITES = 'manage_sites';
    public const PERM_MANAGE_CHANNELS = 'manage_channels';
    public const PERM_ACKNOWLEDGE_INCIDENTS = 'acknowledge_incidents';
    public const PERM_MANAGE_USERS = 'manage_users';
    public const PERM_MANAGE_PLAN = 'manage_plan';
    public const PERM_VIEW_AUDIT = 'view_audit';
    public const PERM_VIEW = 'view';

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        OrganizationUser::ROLE_OWNER => [
            self::PERM_MANAGE_SITES,
            self::PERM_MANAGE_CHANNELS,
            self::PERM_MANAGE_USERS,
            self::PERM_MANAGE_PLAN,
            self::PERM_VIEW_AUDIT,
            self::PERM_ACKNOWLEDGE_INCIDENTS,
            self::PERM_VIEW,
        ],
        OrganizationUser::ROLE_ADMIN => [
            self::PERM_MANAGE_SITES,
            self::PERM_MANAGE_CHANNELS,
            self::PERM_MANAGE_USERS,
            self::PERM_VIEW_AUDIT,
            self::PERM_ACKNOWLEDGE_INCIDENTS,
            self::PERM_VIEW,
        ],
        OrganizationUser::ROLE_INTEGRATOR => [
            self::PERM_MANAGE_SITES,
            self::PERM_VIEW,
        ],
        OrganizationUser::ROLE_OPERATOR => [
            self::PERM_ACKNOWLEDGE_INCIDENTS,
            self::PERM_VIEW,
        ],
        OrganizationUser::ROLE_VIEWER => [
            self::PERM_VIEW,
        ],
    ];

    public function __construct(
        private readonly OrganizationUserRepository $organizationUserRepository,
    ) {
    }

    public function can(User $user, string $permission): bool
    {
        $organizationUser = $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId());
        if ($organizationUser === null) {
            return false;
        }

        $permissions = self::ROLE_PERMISSIONS[$organizationUser->getRole()] ?? [];

        return in_array($permission, $permissions, true);
    }

    public function assertCan(User $user, string $permission): void
    {
        if (!$this->can($user, $permission)) {
            throw new AccessDeniedException('You do not have permission to perform this action.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OrganizationUser;
use App\Entity\User;
use App\Repository\OrganizationUserRepository;
use App\Repository\UserRepository;
use App\Service\Audit\AuditLogService;
use App\Service\Billing\PlanLimitExceededException;
use App\Service\Billing\PlanLimitService;
use App\Service\Security\AccessDeniedException;
use App\Service\Security\OrganizationAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/organization/users')]
final class OrganizationUserController extends AbstractController
{
    private const ASSIGNABLE_ROLES = [
        OrganizationUser::ROLE_ADMIN,
        OrganizationUser::ROLE_INTEGRATOR,
        OrganizationUser::ROLE_OPERATOR,
        OrganizationUser::ROLE_VIEWER,
    ];

    public function __construct(
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly UserRepository $userRepository,
        private readonly PlanLimitService $planLimitService,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'organization_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $members = $this->organizationUserRepository->findAllByOrganization($organization);

        return $this->json([
            'items' => array_map(fn (OrganizationUser $member) => $this->serializeMember($member), $members),
        ]);
    }

    #[Route('', name: 'organization_users_invite', methods: ['POST'])]
    public function invite(Request $request): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($actor, OrganizationAccessService::PERM_MANAGE_USERS);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->planLimitService->assertCanAddUser($organization);
        } catch (PlanLimitExceededException $exception) {
            return $this->error('plan_limit_exceeded', $exception->getMessage(), Response::HTTP_PAYMENT_REQUIRED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = (string) ($data['role'] ?? OrganizationUser::ROLE_VIEWER);

        if ($email === '' || $name === '') {
            return $this->error('validation_failed', 'email and name are required.', Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            return $this->error('validation_failed', 'Invalid role.', Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneByEmail($email);
        if ($user === null) {
            if ($password === '') {
                return $this->error('validation_failed', 'password is required for new users.', Response::HTTP_BAD_REQUEST);
            }
            $user = new User($email, '', $name);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
            $user->setApiToken(bin2hex(random_bytes(32)));
            $this->entityManager->persist($user);
        }

        $existingMembership = $this->organizationUserRepository->findMembership($organization, (string) $user->getId());
        if ($existingMembership !== null) {
            return $this->error('user_exists', 'User is already a member of this organization.', Response::HTTP_CONFLICT);
        }

        $membership = new OrganizationUser($organization, $user, $role);
        $this->entityManager->persist($membership);

        $this->auditLogService->record(
            $organization,
            $actor,
            AuditLogService::ACTION_USER_INVITED,
            'user',
            (string) $user->getId(),
            sprintf('User %s invited with role %s', $email, $role),
            ['email' => $email, 'role' => $role],
        );

        $this->entityManager->flush();

        return $this->json($this->serializeMember($membership), Response::HTTP_CREATED);
    }

    #[Route('/{userId}', name: 'organization_users_update', methods: ['PATCH'])]
    public function updateRole(string $userId, Request $request): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($actor, OrganizationAccessService::PERM_MANAGE_USERS);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        $membership = $organization !== null
            ? $this->organizationUserRepository->findMembership($organization, $userId)
            : null;

        if ($membership === null) {
            return $this->error('user_not_found', 'Organization member was not found.', Response::HTTP_NOT_FOUND);
        }

        if ($membership->getRole() === OrganizationUser::ROLE_OWNER) {
            return $this->error('access_denied', 'Owner role cannot be changed.', Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $role = is_array($data) ? (string) ($data['role'] ?? '') : '';

        if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            return $this->error('validation_failed', 'Invalid role.', Response::HTTP_BAD_REQUEST);
        }

        $oldRole = $membership->getRole();
        $membership->setRole($role);

        $this->auditLogService->record(
            $organization,
            $actor,
            AuditLogService::ACTION_USER_ROLE_UPDATED,
            'user',
            $userId,
            sprintf('User role changed from %s to %s', $oldRole, $role),
            ['oldRole' => $oldRole, 'newRole' => $role],
        );

        $this->entityManager->flush();

        return $this->json($this->serializeMember($membership));
    }

    #[Route('/{userId}', name: 'organization_users_remove', methods: ['DELETE'])]
    public function remove(string $userId): JsonResponse
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($actor, OrganizationAccessService::PERM_MANAGE_USERS);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        $membership = $organization !== null
            ? $this->organizationUserRepository->findMembership($organization, $userId)
            : null;

        if ($membership === null) {
            return $this->error('user_not_found', 'Organization member was not found.', Response::HTTP_NOT_FOUND);
        }

        if ($membership->getRole() === OrganizationUser::ROLE_OWNER) {
            return $this->error('access_denied', 'Owner cannot be removed.', Response::HTTP_FORBIDDEN);
        }

        if ($membership->getUser()->getId()->equals($actor->getId())) {
            return $this->error('access_denied', 'You cannot remove yourself.', Response::HTTP_FORBIDDEN);
        }

        $this->auditLogService->record(
            $organization,
            $actor,
            AuditLogService::ACTION_USER_REMOVED,
            'user',
            $userId,
            sprintf('User %s removed from organization', $membership->getUser()->getEmail()),
            ['email' => $membership->getUser()->getEmail(), 'role' => $membership->getRole()],
        );

        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        return $this->json(['status' => 'removed']);
    }

    private function getCurrentOrganization()
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId())?->getOrganization();
    }

    private function serializeMember(OrganizationUser $member): array
    {
        return [
            'userId' => (string) $member->getUser()->getId(),
            'email' => $member->getUser()->getEmail(),
            'name' => $member->getUser()->getName(),
            'role' => $member->getRole(),
            'joinedAt' => $member->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => ['code' => $code, 'message' => $message],
            'requestId' => bin2hex(random_bytes(8)),
        ], $status);
    }
}

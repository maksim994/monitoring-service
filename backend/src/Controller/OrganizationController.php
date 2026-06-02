<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\NotificationDeliveryRepository;
use App\Repository\OrganizationUserRepository;
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
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/organization')]
final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly PlanLimitService $planLimitService,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly NotificationDeliveryRepository $notificationDeliveryRepository,
        private readonly AuditLogService $auditLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/plan', name: 'organization_plan', methods: ['GET'])]
    public function plan(): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->planLimitService->getUsage($organization));
    }

    #[Route('/plan/change', name: 'organization_plan_change', methods: ['POST'])]
    public function changePlan(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_MANAGE_PLAN);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $planCode = trim((string) ($data['planCode'] ?? ''));
        if ($planCode === '') {
            return $this->error('validation_failed', 'planCode is required.', Response::HTTP_BAD_REQUEST);
        }

        if ($planCode === $organization->getPlanCode()) {
            return $this->json($this->planLimitService->getUsage($organization));
        }

        try {
            $this->planLimitService->assertCanChangePlan($organization, $planCode);
        } catch (\InvalidArgumentException $exception) {
            return $this->error('validation_failed', $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (PlanLimitExceededException $exception) {
            return $this->error('plan_limit_exceeded', $exception->getMessage(), Response::HTTP_PAYMENT_REQUIRED);
        }

        $oldPlan = $organization->getPlanCode();
        $organization->setPlanCode($planCode);

        $this->auditLogService->record(
            $organization,
            $user,
            AuditLogService::ACTION_PLAN_CHANGED,
            'organization',
            (string) $organization->getId(),
            sprintf('Plan changed from %s to %s', $oldPlan, $planCode),
            ['oldPlan' => $oldPlan, 'newPlan' => $planCode],
        );

        $this->entityManager->flush();

        return $this->json($this->planLimitService->getUsage($organization));
    }

    #[Route('/audit-logs', name: 'organization_audit_logs', methods: ['GET'])]
    public function auditLogs(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_VIEW_AUDIT);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $logs = $this->auditLogRepository->findByOrganization($organization);

        return $this->json([
            'items' => array_map(static fn ($log) => [
                'id' => (string) $log->getId(),
                'action' => $log->getAction(),
                'targetType' => $log->getTargetType(),
                'targetId' => $log->getTargetId(),
                'message' => $log->getMessage(),
                'actorUserId' => $log->getActorUserId() !== null ? (string) $log->getActorUserId() : null,
                'createdAt' => $log->getCreatedAt()->format(DATE_ATOM),
            ], $logs),
        ]);
    }

    #[Route('/notification-deliveries', name: 'organization_notification_deliveries', methods: ['GET'])]
    public function notificationDeliveries(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_VIEW_AUDIT);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $deliveries = $this->notificationDeliveryRepository->findByOrganization($organization);

        return $this->json([
            'items' => array_map(static fn ($delivery) => [
                'id' => (string) $delivery->getId(),
                'channelId' => (string) $delivery->getChannel()->getId(),
                'channelName' => $delivery->getChannel()->getName(),
                'channelType' => $delivery->getChannel()->getType(),
                'incidentId' => $delivery->getIncidentId() !== null ? (string) $delivery->getIncidentId() : null,
                'status' => $delivery->getStatus(),
                'error' => $delivery->getError(),
                'sentAt' => $delivery->getSentAt()?->format(DATE_ATOM),
                'createdAt' => $delivery->getCreatedAt()->format(DATE_ATOM),
            ], $deliveries),
        ]);
    }

    private function getCurrentOrganization()
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId())?->getOrganization();
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => ['code' => $code, 'message' => $message],
            'requestId' => bin2hex(random_bytes(8)),
        ], $status);
    }
}

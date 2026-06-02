<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Organization;
use App\Entity\Site;
use App\Repository\CheckRepository;
use App\Repository\IncidentRepository;
use App\Repository\OrganizationRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\PlanRepository;
use App\Repository\SiteRepository;
use App\Service\Audit\AuditLogService;
use App\Service\Billing\PlanLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/admin/organizations')]
final class AdminOrganizationController extends AbstractAdminController
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly SiteRepository $siteRepository,
        private readonly IncidentRepository $incidentRepository,
        private readonly PlanRepository $planRepository,
        private readonly PlanLimitService $planLimitService,
        private readonly AuditLogService $auditLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_organizations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $items = [];
        foreach ($this->organizationRepository->findAllOrdered() as $organization) {
            $items[] = $this->serializeSummary($organization);
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/{organizationId}', name: 'admin_organizations_show', methods: ['GET'])]
    public function show(string $organizationId): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $organization = $this->findOrganization($organizationId);
        if ($organization instanceof JsonResponse) {
            return $organization;
        }

        $sites = $this->siteRepository->findByOrganization($organization);

        return $this->json([
            ...$this->serializeSummary($organization),
            'usage' => $this->planLimitService->getUsage($organization),
            'sites' => array_map(fn (Site $site) => $this->serializeSite($site), $sites),
        ]);
    }

    #[Route('/{organizationId}', name: 'admin_organizations_update', methods: ['PATCH'])]
    public function update(string $organizationId, Request $request): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $organization = $this->findOrganization($organizationId);
        if ($organization instanceof JsonResponse) {
            return $organization;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $actor = $this->getUser();
        $changes = [];

        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                return $this->error('validation_failed', 'name cannot be empty.', Response::HTTP_BAD_REQUEST);
            }
            $organization->setName($name);
            $changes['name'] = $name;
        }

        if (isset($data['planCode'])) {
            $planCode = trim((string) $data['planCode']);
            if ($this->planRepository->findOneByCode($planCode) === null) {
                return $this->error('validation_failed', 'Invalid plan code.', Response::HTTP_BAD_REQUEST);
            }
            $oldPlan = $organization->getPlanCode();
            $organization->setPlanCode($planCode);
            $changes['planCode'] = ['old' => $oldPlan, 'new' => $planCode];
        }

        if (isset($data['status'])) {
            $status = trim((string) $data['status']);
            if (!in_array($status, [Organization::STATUS_ACTIVE, Organization::STATUS_SUSPENDED], true)) {
                return $this->error('validation_failed', 'Invalid status.', Response::HTTP_BAD_REQUEST);
            }
            $organization->setStatus($status);
            $changes['status'] = $status;
        }

        if ($changes !== [] && $actor instanceof \App\Entity\User) {
            $this->auditLogService->record(
                $organization,
                $actor,
                AuditLogService::ACTION_ORGANIZATION_UPDATED,
                'organization',
                (string) $organization->getId(),
                sprintf('Organization %s updated by platform admin', $organization->getName()),
                $changes,
            );
        }

        $this->entityManager->flush();

        return $this->json($this->serializeSummary($organization));
    }

    private function findOrganization(string $organizationId): Organization|JsonResponse
    {
        if (!Uuid::isValid($organizationId)) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $organization = $this->organizationRepository->find(Uuid::fromString($organizationId));
        if (!$organization instanceof Organization) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        return $organization;
    }

    /** @return array<string, mixed> */
    private function serializeSummary(Organization $organization): array
    {
        $config = $this->planLimitService->getPlanConfig($organization);

        return [
            'id' => (string) $organization->getId(),
            'name' => $organization->getName(),
            'planCode' => $organization->getPlanCode(),
            'planLabel' => $config['label'],
            'status' => $organization->getStatus(),
            'sitesCount' => count($this->siteRepository->findByOrganization($organization)),
            'activeSitesCount' => $this->siteRepository->countActiveByOrganization($organization),
            'usersCount' => $this->organizationUserRepository->countByOrganization($organization),
            'openIncidents' => $this->incidentRepository->countOpenByOrganization($organization),
            'createdAt' => $organization->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSite(Site $site): array
    {
        return [
            'id' => (string) $site->getId(),
            'domain' => $site->getDomain(),
            'siteUrl' => $site->getSiteUrl(),
            'status' => $site->getStatus(),
            'lastHeartbeatAt' => $site->getLastHeartbeatAt()?->format(DATE_ATOM),
            'openIncidents' => $this->incidentRepository->countOpenBySite($site),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Site;
use App\Repository\CheckRepository;
use App\Repository\IncidentRepository;
use App\Repository\SiteRepository;
use App\Service\Alert\SiteStatusResolver;
use App\Service\Audit\AuditLogService;
use App\Service\Billing\PlanLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/admin/sites')]
final class AdminSiteController extends AbstractAdminController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly CheckRepository $checkRepository,
        private readonly IncidentRepository $incidentRepository,
        private readonly PlanLimitService $planLimitService,
        private readonly SiteStatusResolver $siteStatusResolver,
        private readonly AuditLogService $auditLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_sites_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $items = [];
        foreach ($this->siteRepository->findAllOrdered() as $site) {
            $items[] = $this->serializeSite($site);
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/{siteId}', name: 'admin_sites_show', methods: ['GET'])]
    public function show(string $siteId): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $site = $this->findSite($siteId);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        return $this->json([
            ...$this->serializeSite($site),
            'moduleVersion' => $site->getModuleVersion(),
            'bitrixVersion' => $site->getBitrixVersion(),
            'phpVersion' => $site->getPhpVersion(),
            'checks' => array_map(static fn ($check) => [
                'id' => (string) $check->getId(),
                'type' => $check->getType(),
                'enabled' => $check->isEnabled(),
                'intervalSeconds' => $check->getIntervalSeconds(),
            ], $this->checkRepository->findBySite($site)),
        ]);
    }

    #[Route('/{siteId}', name: 'admin_sites_update', methods: ['PATCH'])]
    public function update(string $siteId, Request $request): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $site = $this->findSite($siteId);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['status'])) {
            return $this->error('validation_failed', 'status is required.', Response::HTTP_BAD_REQUEST);
        }

        $status = trim((string) $data['status']);
        $organization = $site->getOrganization();

        if ($status === Site::STATUS_DISABLED) {
            if ($site->getStatus() === Site::STATUS_DISABLED) {
                return $this->error('invalid_state', 'Site is already disabled.', Response::HTTP_CONFLICT);
            }
            $site->setStatus(Site::STATUS_DISABLED);
            foreach ($this->checkRepository->findBySite($site) as $check) {
                $check->setEnabled(false);
            }
            $action = AuditLogService::ACTION_SITE_DISABLED;
            $message = sprintf('Site %s disabled by platform admin', $site->getDomain());
        } elseif ($status === Site::STATUS_OK || $status === Site::STATUS_PENDING) {
            if ($site->getStatus() !== Site::STATUS_DISABLED) {
                return $this->error('invalid_state', 'Site is not disabled.', Response::HTTP_CONFLICT);
            }
            try {
                $this->planLimitService->assertCanCreateSite($organization);
            } catch (\App\Service\Billing\PlanLimitExceededException $exception) {
                return $this->error('plan_limit_exceeded', $exception->getMessage(), Response::HTTP_PAYMENT_REQUIRED);
            }
            $site->setStatus($site->getLastHeartbeatAt() !== null ? Site::STATUS_OK : Site::STATUS_PENDING);
            foreach ($this->checkRepository->findBySite($site) as $check) {
                $check->setEnabled(true);
            }
            $this->siteStatusResolver->sync($site);
            $action = AuditLogService::ACTION_SITE_ENABLED;
            $message = sprintf('Site %s enabled by platform admin', $site->getDomain());
        } else {
            return $this->error('validation_failed', 'Invalid status.', Response::HTTP_BAD_REQUEST);
        }

        $actor = $this->getUser();
        if ($actor instanceof \App\Entity\User) {
            $this->auditLogService->record(
                $organization,
                $actor,
                $action,
                'site',
                (string) $site->getId(),
                $message,
                ['domain' => $site->getDomain(), 'status' => $status],
            );
        }

        $this->entityManager->flush();

        return $this->json($this->serializeSite($site));
    }

    private function findSite(string $siteId): Site|JsonResponse
    {
        if (!Uuid::isValid($siteId)) {
            return $this->error('site_not_found', 'Site was not found.', Response::HTTP_NOT_FOUND);
        }

        $site = $this->siteRepository->find(Uuid::fromString($siteId));
        if (!$site instanceof Site) {
            return $this->error('site_not_found', 'Site was not found.', Response::HTTP_NOT_FOUND);
        }

        return $site;
    }

    /** @return array<string, mixed> */
    private function serializeSite(Site $site): array
    {
        $organization = $site->getOrganization();

        return [
            'id' => (string) $site->getId(),
            'organizationId' => (string) $organization->getId(),
            'organizationName' => $organization->getName(),
            'domain' => $site->getDomain(),
            'siteUrl' => $site->getSiteUrl(),
            'status' => $site->getStatus(),
            'lastHeartbeatAt' => $site->getLastHeartbeatAt()?->format(DATE_ATOM),
            'openIncidents' => $this->incidentRepository->countOpenBySite($site),
            'createdAt' => $site->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}

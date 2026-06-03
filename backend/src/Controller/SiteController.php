<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Site;
use App\Entity\User;
use App\Repository\CheckRepository;
use App\Repository\IncidentRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\SiteRepository;
use App\Service\Alert\SiteStatusResolver;
use App\Service\Audit\AuditLogService;
use App\Service\Billing\PlanLimitExceededException;
use App\Service\Billing\PlanLimitService;
use App\Entity\Check;
use App\Service\Check\CheckProvisioner;
use App\Service\Check\CheckSnapshotService;
use App\Service\Security\AccessDeniedException;
use App\Service\Security\OrganizationAccessService;
use App\Service\Security\SiteKeyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/sites')]
final class SiteController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly CheckRepository $checkRepository,
        private readonly IncidentRepository $incidentRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly SiteKeyService $siteKeyService,
        private readonly CheckProvisioner $checkProvisioner,
        private readonly CheckSnapshotService $checkSnapshotService,
        private readonly PlanLimitService $planLimitService,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly SiteStatusResolver $siteStatusResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'sites_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $sites = $this->siteRepository->findByOrganization($organization);

        return $this->json([
            'items' => array_map(fn (Site $site) => $this->serializeSiteSummary($site), $sites),
        ]);
    }

    #[Route('', name: 'sites_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_MANAGE_SITES);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->planLimitService->assertCanCreateSite($organization);
        } catch (PlanLimitExceededException $exception) {
            return $this->error('plan_limit_exceeded', $exception->getMessage(), Response::HTTP_PAYMENT_REQUIRED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $domain = trim((string) ($data['domain'] ?? ''));
        $siteUrl = trim((string) ($data['siteUrl'] ?? ''));

        if ($domain === '' || $siteUrl === '') {
            return $this->error('validation_failed', 'domain and siteUrl are required.', Response::HTTP_BAD_REQUEST);
        }

        $site = new Site($organization, $domain, $siteUrl);
        $this->entityManager->persist($site);
        $this->checkProvisioner->provisionForSite($site);
        $keyData = $this->siteKeyService->createKey($site);

        $this->auditLogService->record(
            $organization,
            $user,
            AuditLogService::ACTION_SITE_CREATED,
            'site',
            (string) $site->getId(),
            sprintf('Site %s created', $domain),
            ['domain' => $domain, 'siteUrl' => $siteUrl],
        );

        $this->entityManager->flush();

        return $this->json([
            'siteId' => (string) $site->getId(),
            'apiSecret' => $keyData['secret'],
            'site' => $this->serializeSiteDetails($site),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{siteId}', name: 'sites_show', methods: ['GET'])]
    public function show(string $siteId): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        $site = $this->siteRepository->find(Uuid::fromString($siteId));

        if (!$site instanceof Site || $organization === null || !$site->getOrganization()->getId()->equals($organization->getId())) {
            return $this->error('site_not_found', 'Site was not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            ...$this->serializeSiteDetails($site),
            'checks' => array_map(
                fn (Check $check) => $this->serializeCheck($check),
                $this->checkRepository->findBySite($site),
            ),
        ]);
    }

    #[Route('/{siteId}/rotate-key', name: 'sites_rotate_key', methods: ['POST'])]
    public function rotateKey(string $siteId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_MANAGE_SITES);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        $site = $this->findOrganizationSite($siteId, $organization);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return $this->error('invalid_state', 'Cannot rotate key for a disabled site.', Response::HTTP_CONFLICT);
        }

        $keyData = $this->siteKeyService->rotateKey($site);

        $this->auditLogService->record(
            $organization,
            $user,
            AuditLogService::ACTION_SITE_KEY_ROTATED,
            'site',
            (string) $site->getId(),
            sprintf('API key rotated for site %s', $site->getDomain()),
            ['domain' => $site->getDomain()],
        );

        $this->entityManager->flush();

        return $this->json([
            'siteId' => (string) $site->getId(),
            'apiSecret' => $keyData['secret'],
        ]);
    }

    #[Route('/{siteId}/disable', name: 'sites_disable', methods: ['POST'])]
    public function disable(string $siteId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_MANAGE_SITES);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        $site = $this->findOrganizationSite($siteId, $organization);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return $this->error('invalid_state', 'Site is already disabled.', Response::HTTP_CONFLICT);
        }

        $site->setStatus(Site::STATUS_DISABLED);
        foreach ($this->checkRepository->findBySite($site) as $check) {
            $check->setEnabled(false);
        }

        $this->auditLogService->record(
            $organization,
            $user,
            AuditLogService::ACTION_SITE_DISABLED,
            'site',
            (string) $site->getId(),
            sprintf('Site %s disabled', $site->getDomain()),
            ['domain' => $site->getDomain()],
        );

        $this->entityManager->flush();

        return $this->json($this->serializeSiteDetails($site));
    }

    #[Route('/{siteId}/enable', name: 'sites_enable', methods: ['POST'])]
    public function enable(string $siteId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_MANAGE_SITES);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        $site = $this->findOrganizationSite($siteId, $organization);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        if ($site->getStatus() !== Site::STATUS_DISABLED) {
            return $this->error('invalid_state', 'Site is not disabled.', Response::HTTP_CONFLICT);
        }

        try {
            $this->planLimitService->assertCanCreateSite($organization);
        } catch (PlanLimitExceededException $exception) {
            return $this->error('plan_limit_exceeded', $exception->getMessage(), Response::HTTP_PAYMENT_REQUIRED);
        }

        $site->setStatus($site->getLastHeartbeatAt() !== null ? Site::STATUS_OK : Site::STATUS_PENDING);
        foreach ($this->checkRepository->findBySite($site) as $check) {
            $check->setEnabled(true);
        }
        $this->siteStatusResolver->sync($site);

        $this->auditLogService->record(
            $organization,
            $user,
            AuditLogService::ACTION_SITE_ENABLED,
            'site',
            (string) $site->getId(),
            sprintf('Site %s enabled', $site->getDomain()),
            ['domain' => $site->getDomain()],
        );

        $this->entityManager->flush();

        return $this->json($this->serializeSiteDetails($site));
    }

    private function findOrganizationSite(string $siteId, $organization): Site|JsonResponse
    {
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $site = $this->siteRepository->find(Uuid::fromString($siteId));
        if (!$site instanceof Site || !$site->getOrganization()->getId()->equals($organization->getId())) {
            return $this->error('site_not_found', 'Site was not found.', Response::HTTP_NOT_FOUND);
        }

        return $site;
    }

    private function getCurrentOrganization()
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $organizationUser = $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId());

        return $organizationUser?->getOrganization();
    }

    private function serializeSiteSummary(Site $site): array
    {
        return [
            'id' => (string) $site->getId(),
            'domain' => $site->getDomain(),
            'status' => $site->getStatus(),
            'lastHeartbeatAt' => $site->getLastHeartbeatAt()?->format(DATE_ATOM),
            'openIncidents' => $this->incidentRepository->countOpenBySite($site),
        ];
    }

    private function serializeSiteDetails(Site $site): array
    {
        return [
            ...$this->serializeSiteSummary($site),
            'siteUrl' => $site->getSiteUrl(),
            'moduleVersion' => $site->getModuleVersion(),
            'bitrixVersion' => $site->getBitrixVersion(),
            'phpVersion' => $site->getPhpVersion(),
            'configVersion' => $site->getConfigVersion(),
        ];
    }

    private function serializeCheck(Check $check): array
    {
        $snapshot = $this->checkSnapshotService->resolveForApi($check);

        return [
            'id' => (string) $check->getId(),
            'type' => $check->getType(),
            'enabled' => $check->isEnabled(),
            'intervalSeconds' => $check->getIntervalSeconds(),
            'settings' => $check->getSettingsJson(),
            'snapshot' => $snapshot,
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'requestId' => bin2hex(random_bytes(8)),
        ], $status);
    }
}

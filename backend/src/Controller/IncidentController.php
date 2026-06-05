<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Incident;
use App\Entity\IncidentEvent;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\IncidentRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\SiteRepository;
use App\Service\Alert\SiteStatusResolver;
use App\Service\Audit\AuditLogService;
use App\Service\Security\AccessDeniedException;
use App\Service\Security\OrganizationAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/incidents')]
final class IncidentController extends AbstractController
{
    public function __construct(
        private readonly IncidentRepository $incidentRepository,
        private readonly SiteRepository $siteRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly SiteStatusResolver $siteStatusResolver,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'incidents_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $status = $request->query->get('status');
        $statusFilter = is_string($status) && $status !== '' ? $status : null;
        $siteFilter = null;
        $siteId = $request->query->get('siteId');
        if (is_string($siteId) && $siteId !== '') {
            $siteFilter = $this->siteRepository->find(Uuid::fromString($siteId));
            if (!$siteFilter instanceof Site || !$siteFilter->getOrganization()->getId()->equals($organization->getId())) {
                return $this->error('site_not_found', 'Site was not found.', Response::HTTP_NOT_FOUND);
            }
        }

        $incidents = $this->incidentRepository->findByOrganization($organization, $statusFilter, $siteFilter);

        return $this->json([
            'items' => array_map(fn (Incident $incident) => $this->serializeIncident($incident), $incidents),
        ]);
    }

    #[Route('/{incidentId}', name: 'incidents_show', methods: ['GET'])]
    public function show(string $incidentId): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        $incident = $this->incidentRepository->find(Uuid::fromString($incidentId));

        if (!$incident instanceof Incident || $organization === null || !$incident->getOrganization()->getId()->equals($organization->getId())) {
            return $this->error('incident_not_found', 'Incident was not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeIncidentDetails($incident));
    }

    #[Route('/{incidentId}/acknowledge', name: 'incidents_acknowledge', methods: ['POST'])]
    public function acknowledge(string $incidentId): JsonResponse
    {
        return $this->transition($incidentId, function (Incident $incident, User $user): JsonResponse {
            if (!$incident->isActive()) {
                return $this->error('invalid_state', 'Incident is not active.', Response::HTTP_CONFLICT);
            }

            $incident->acknowledge();
            $this->entityManager->persist(new IncidentEvent(
                $incident,
                IncidentEvent::TYPE_ACKNOWLEDGED,
                'Инцидент подтверждён оператором',
                [],
                $user->getId(),
            ));

            $this->auditLogService->record(
                $incident->getOrganization(),
                $user,
                AuditLogService::ACTION_INCIDENT_ACKNOWLEDGED,
                'incident',
                (string) $incident->getId(),
                sprintf('Incident acknowledged: %s', $incident->getTitle()),
                ['severity' => $incident->getSeverity(), 'checkType' => $incident->getCheckType()],
            );

            return $this->json($this->serializeIncidentDetails($incident));
        });
    }

    #[Route('/{incidentId}/resolve', name: 'incidents_resolve', methods: ['POST'])]
    public function resolve(string $incidentId): JsonResponse
    {
        return $this->transition($incidentId, function (Incident $incident, User $user): JsonResponse {
            if (!$incident->isActive()) {
                return $this->error('invalid_state', 'Incident is not active.', Response::HTTP_CONFLICT);
            }

            $incident->resolve();
            $this->entityManager->persist(new IncidentEvent(
                $incident,
                IncidentEvent::TYPE_RESOLVED,
                'Инцидент закрыт вручную',
                [],
                $user->getId(),
            ));
            $this->siteStatusResolver->sync($incident->getSite());

            $this->auditLogService->record(
                $incident->getOrganization(),
                $user,
                AuditLogService::ACTION_INCIDENT_RESOLVED,
                'incident',
                (string) $incident->getId(),
                sprintf('Incident resolved: %s', $incident->getTitle()),
                ['severity' => $incident->getSeverity(), 'checkType' => $incident->getCheckType()],
            );

            return $this->json($this->serializeIncidentDetails($incident));
        });
    }

    private function transition(string $incidentId, callable $handler): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        $user = $this->getUser();
        $incident = $this->incidentRepository->find(Uuid::fromString($incidentId));

        if (!$incident instanceof Incident || $organization === null || !$incident->getOrganization()->getId()->equals($organization->getId())) {
            return $this->error('incident_not_found', 'Incident was not found.', Response::HTTP_NOT_FOUND);
        }

        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_ACKNOWLEDGE_INCIDENTS);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $response = $handler($incident, $user);
        $this->entityManager->flush();

        return $response;
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

    private function serializeIncident(Incident $incident): array
    {
        return [
            'id' => (string) $incident->getId(),
            'siteId' => (string) $incident->getSite()->getId(),
            'siteDomain' => $incident->getSite()->getDomain(),
            'checkType' => $incident->getCheckType(),
            'severity' => $incident->getSeverity(),
            'status' => $incident->getStatus(),
            'title' => $incident->getTitle(),
            'openedAt' => $incident->getOpenedAt()->format(DATE_ATOM),
            'acknowledgedAt' => $incident->getAcknowledgedAt()?->format(DATE_ATOM),
            'resolvedAt' => $incident->getResolvedAt()?->format(DATE_ATOM),
        ];
    }

    private function serializeIncidentDetails(Incident $incident): array
    {
        return [
            ...$this->serializeIncident($incident),
            'evidence' => $incident->getLastEvidenceJson(),
            'updatedAt' => $incident->getUpdatedAt()->format(DATE_ATOM),
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

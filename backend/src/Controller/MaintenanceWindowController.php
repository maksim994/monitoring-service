<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MaintenanceWindow;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\MaintenanceWindowRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\SiteRepository;
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

#[Route('/api/v1/sites/{siteId}/maintenance-windows')]
final class MaintenanceWindowController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly MaintenanceWindowRepository $maintenanceWindowRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'maintenance_windows_list', methods: ['GET'])]
    public function list(string $siteId): JsonResponse
    {
        $site = $this->resolveSite($siteId);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $windows = $this->maintenanceWindowRepository->findVisibleBySite($site);

        return $this->json([
            'items' => array_map(fn (MaintenanceWindow $window) => $this->serialize($window), $windows),
        ]);
    }

    #[Route('', name: 'maintenance_windows_create', methods: ['POST'])]
    public function create(string $siteId, Request $request): JsonResponse
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

        $site = $this->resolveSite($siteId);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        try {
            [$startsAt, $endsAt, $title, $checkType] = $this->parseCreatePayload($data);
        } catch (\InvalidArgumentException $exception) {
            return $this->error('validation_failed', $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $window = new MaintenanceWindow(
            $site->getOrganization(),
            $site,
            $title,
            $startsAt,
            $endsAt,
            $checkType,
            $user->getId(),
        );

        $this->entityManager->persist($window);

        $this->auditLogService->record(
            $site->getOrganization(),
            $user,
            AuditLogService::ACTION_MAINTENANCE_CREATED,
            'maintenance_window',
            (string) $window->getId(),
            sprintf('Maintenance window for %s until %s', $site->getDomain(), $endsAt->format('Y-m-d H:i')),
            [
                'siteId' => (string) $site->getId(),
                'checkType' => $checkType,
                'endsAt' => $endsAt->format(DATE_ATOM),
            ],
        );

        $this->entityManager->flush();

        return $this->json($this->serialize($window), Response::HTTP_CREATED);
    }

    #[Route('/{windowId}/cancel', name: 'maintenance_windows_cancel', methods: ['POST'])]
    public function cancel(string $siteId, string $windowId): JsonResponse
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

        $site = $this->resolveSite($siteId);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $window = $this->maintenanceWindowRepository->find(Uuid::fromString($windowId));
        if (
            !$window instanceof MaintenanceWindow
            || !$window->getSite()->getId()->equals($site->getId())
        ) {
            return $this->error('maintenance_not_found', 'Maintenance window was not found.', Response::HTTP_NOT_FOUND);
        }

        if ($window->isCancelled()) {
            return $this->error('invalid_state', 'Maintenance window is already cancelled.', Response::HTTP_CONFLICT);
        }

        $window->cancel();

        $this->auditLogService->record(
            $site->getOrganization(),
            $user,
            AuditLogService::ACTION_MAINTENANCE_CANCELLED,
            'maintenance_window',
            (string) $window->getId(),
            sprintf('Maintenance window cancelled for %s', $site->getDomain()),
            ['siteId' => (string) $site->getId()],
        );

        $this->entityManager->flush();

        return $this->json($this->serialize($window));
    }

    /** @param array<string, mixed> $data */
    private function parseCreatePayload(array $data): array
    {
        $title = trim((string) ($data['title'] ?? 'Плановые работы'));
        if ($title === '') {
            $title = 'Плановые работы';
        }

        $checkType = trim((string) ($data['checkType'] ?? ''));
        $checkType = $checkType !== '' ? $checkType : null;

        $startsAt = $this->parseDateTime($data['startsAt'] ?? null) ?? new \DateTimeImmutable();
        $endsAt = $this->parseDateTime($data['endsAt'] ?? null);

        if ($endsAt === null && isset($data['durationHours']) && is_numeric($data['durationHours'])) {
            $hours = max(1, (int) $data['durationHours']);
            $endsAt = $startsAt->modify(sprintf('+%d hours', $hours));
        }

        if ($endsAt === null) {
            throw new \InvalidArgumentException('endsAt or durationHours is required.');
        }

        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('endsAt must be after startsAt.');
        }

        return [$startsAt, $endsAt, $title, $checkType];
    }

    private function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid datetime format.');
        }
    }

    private function resolveSite(string $siteId): Site|JsonResponse
    {
        $organization = $this->getCurrentOrganization();
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

        return $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId())?->getOrganization();
    }

    private function serialize(MaintenanceWindow $window): array
    {
        return [
            'id' => (string) $window->getId(),
            'siteId' => (string) $window->getSite()->getId(),
            'title' => $window->getTitle(),
            'checkType' => $window->getCheckType(),
            'startsAt' => $window->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $window->getEndsAt()->format(DATE_ATOM),
            'cancelledAt' => $window->getCancelledAt()?->format(DATE_ATOM),
            'active' => $window->isActive(),
            'scheduled' => $window->isScheduled(),
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

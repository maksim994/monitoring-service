<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Check;
use App\Entity\User;
use App\Entity\Site;
use App\Repository\CheckRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\SiteRepository;
use App\Service\Alert\AlertEngine;
use App\Service\Check\CheckSettingsValidator;
use App\Service\Check\CheckSnapshotService;
use App\Service\Security\AccessDeniedException;
use App\Service\Security\OrganizationAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/sites/{siteId}/checks')]
final class CheckController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly CheckRepository $checkRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly CheckSettingsValidator $checkSettingsValidator,
        private readonly AlertEngine $alertEngine,
        private readonly CheckSnapshotService $checkSnapshotService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/{checkId}', name: 'checks_update', methods: ['PATCH'])]
    public function update(Request $request, string $siteId, string $checkId): JsonResponse
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

        $check = $this->checkRepository->find(Uuid::fromString($checkId));
        if ($check === null || !$check->getSite()->getId()->equals($site->getId())) {
            return $this->error('check_not_found', 'Check was not found.', Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->error('invalid_payload', 'Invalid JSON payload.', Response::HTTP_BAD_REQUEST);
        }

        $hasSettings = array_key_exists('settings', $payload) && is_array($payload['settings']);
        $hasEnabled = array_key_exists('enabled', $payload);
        $hasNotificationsEnabled = array_key_exists('notificationsEnabled', $payload);

        if (!$hasSettings && !$hasEnabled && !$hasNotificationsEnabled) {
            return $this->error('invalid_payload', 'Укажите settings, enabled и/или notificationsEnabled.', Response::HTTP_BAD_REQUEST);
        }

        if ($hasEnabled) {
            $enabled = filter_var($payload['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!is_bool($enabled)) {
                return $this->error('invalid_payload', 'Поле enabled должно быть true или false.', Response::HTTP_BAD_REQUEST);
            }

            if ($check->isEnabled() !== $enabled) {
                $check->setEnabled($enabled);
                if (!$enabled) {
                    $this->alertEngine->resolveIncidentsForCheckType($site, $check->getType());
                }
            }
        }

        if ($hasNotificationsEnabled) {
            $notificationsEnabled = filter_var($payload['notificationsEnabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!is_bool($notificationsEnabled)) {
                return $this->error('invalid_payload', 'Поле notificationsEnabled должно быть true или false.', Response::HTTP_BAD_REQUEST);
            }

            $check->setNotificationsEnabled($notificationsEnabled);
        }

        if ($hasSettings) {
            try {
                $merged = $this->checkSettingsValidator->merge(
                    $check->getType(),
                    $check->getSettingsJson(),
                    $payload['settings'],
                );
            } catch (\InvalidArgumentException $exception) {
                return $this->error('invalid_settings', $exception->getMessage(), Response::HTTP_BAD_REQUEST);
            }

            $check->replaceSettingsJson($merged);
        }

        $this->entityManager->flush();

        if ($hasSettings && $check->isEnabled()) {
            $this->alertEngine->reevaluateAfterSettingsChange($site, $check);
            $this->entityManager->flush();
        }

        return $this->json($this->serializeCheck($check));
    }

    private function serializeCheck(Check $check): array
    {
        return [
            'id' => (string) $check->getId(),
            'type' => $check->getType(),
            'enabled' => $check->isEnabled(),
            'notificationsEnabled' => $check->areNotificationsEnabled(),
            'intervalSeconds' => $check->getIntervalSeconds(),
            'settings' => $check->getSettingsJson(),
            'snapshot' => $this->checkSnapshotService->resolveForApi($check),
        ];
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

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => ['code' => $code, 'message' => $message],
            'requestId' => bin2hex(random_bytes(8)),
        ], $status);
    }
}

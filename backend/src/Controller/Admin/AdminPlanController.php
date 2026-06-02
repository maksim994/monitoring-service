<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Plan;
use App\Repository\OrganizationRepository;
use App\Repository\PlanRepository;
use App\Service\Billing\PlanLimitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/plans')]
final class AdminPlanController extends AbstractAdminController
{
    public function __construct(
        private readonly PlanRepository $planRepository,
        private readonly OrganizationRepository $organizationRepository,
        private readonly PlanLimitService $planLimitService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_plans_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        return $this->json([
            'items' => $this->planLimitService->getAvailablePlans(includeInactive: true),
        ]);
    }

    #[Route('', name: 'admin_plans_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $code = strtolower(trim((string) ($data['code'] ?? '')));
        if (!$this->isValidPlanCode($code)) {
            return $this->error('validation_failed', 'code must be lowercase alphanumeric with underscores.', Response::HTTP_BAD_REQUEST);
        }

        if ($this->planRepository->findOneByCode($code) !== null) {
            return $this->error('plan_exists', 'Plan code already exists.', Response::HTTP_CONFLICT);
        }

        $plan = new Plan(
            $code,
            trim((string) ($data['label'] ?? $code)),
            max(1, (int) ($data['maxSites'] ?? 1)),
            max(1, (int) ($data['maxUsers'] ?? 1)),
            max(60, (int) ($data['uptimeIntervalSeconds'] ?? 900)),
            (bool) ($data['webhooksEnabled'] ?? false),
            (int) ($data['sortOrder'] ?? 100),
        );

        $this->entityManager->persist($plan);
        $this->entityManager->flush();

        return $this->json($this->serializePlan($plan), Response::HTTP_CREATED);
    }

    #[Route('/{code}', name: 'admin_plans_update', methods: ['PATCH'])]
    public function update(string $code, Request $request): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $plan = $this->planRepository->findOneByCode($code);
        if ($plan === null) {
            return $this->error('plan_not_found', 'Plan was not found.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['label'])) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                return $this->error('validation_failed', 'label cannot be empty.', Response::HTTP_BAD_REQUEST);
            }
            $plan->setLabel($label);
        }

        if (isset($data['maxSites'])) {
            $plan->setMaxSites(max(1, (int) $data['maxSites']));
        }

        if (isset($data['maxUsers'])) {
            $plan->setMaxUsers(max(1, (int) $data['maxUsers']));
        }

        if (isset($data['uptimeIntervalSeconds'])) {
            $plan->setMinUptimeIntervalSeconds(max(60, (int) $data['uptimeIntervalSeconds']));
        }

        if (isset($data['webhooksEnabled'])) {
            $plan->setWebhooksEnabled((bool) $data['webhooksEnabled']);
        }

        if (isset($data['active'])) {
            $plan->setActive((bool) $data['active']);
        }

        if (isset($data['sortOrder'])) {
            $plan->setSortOrder((int) $data['sortOrder']);
        }

        $this->entityManager->flush();

        return $this->json($this->serializePlan($plan));
    }

    #[Route('/{code}', name: 'admin_plans_delete', methods: ['DELETE'])]
    public function delete(string $code): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $plan = $this->planRepository->findOneByCode($code);
        if ($plan === null) {
            return $this->error('plan_not_found', 'Plan was not found.', Response::HTTP_NOT_FOUND);
        }

        $organizationsUsingPlan = count(array_filter(
            $this->organizationRepository->findAllOrdered(),
            static fn ($organization) => $organization->getPlanCode() === $code,
        ));

        if ($organizationsUsingPlan > 0) {
            return $this->error(
                'plan_in_use',
                sprintf('Cannot delete plan: it is used by %d organizations.', $organizationsUsingPlan),
                Response::HTTP_CONFLICT,
            );
        }

        $this->entityManager->remove($plan);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    private function isValidPlanCode(string $code): bool
    {
        return $code !== '' && (bool) preg_match('/^[a-z0-9_]+$/', $code);
    }

    /** @return array<string, mixed> */
    private function serializePlan(Plan $plan): array
    {
        return [
            'code' => $plan->getCode(),
            'label' => $plan->getLabel(),
            'maxSites' => $plan->getMaxSites(),
            'maxUsers' => $plan->getMaxUsers(),
            'uptimeIntervalSeconds' => $plan->getMinUptimeIntervalSeconds(),
            'webhooksEnabled' => $plan->isWebhooksEnabled(),
            'active' => $plan->isActive(),
            'sortOrder' => $plan->getSortOrder(),
        ];
    }
}

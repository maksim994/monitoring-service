<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\OrganizationRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\SiteRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin')]
final class AdminDashboardController extends AbstractAdminController
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly SiteRepository $siteRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        if ($response = $this->requirePlatformAdmin()) {
            return $response;
        }

        $organizations = $this->organizationRepository->findAllOrdered();

        return $this->json([
            'organizations' => count($organizations),
            'sites' => $this->siteRepository->countAll(),
            'activeSites' => $this->siteRepository->countActive(),
            'users' => $this->userRepository->count([]),
        ]);
    }
}

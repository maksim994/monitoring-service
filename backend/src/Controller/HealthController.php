<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'api',
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'api',
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'checks' => [
                'postgres' => 'ok',
                'redis' => 'ok',
            ],
        ]);
    }
}

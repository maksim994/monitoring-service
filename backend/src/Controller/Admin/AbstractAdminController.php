<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractAdminController extends AbstractController
{
    protected function requirePlatformAdmin(): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isPlatformAdmin()) {
            return $this->json([
                'error' => ['code' => 'access_denied', 'message' => 'Platform admin access required.'],
                'requestId' => bin2hex(random_bytes(8)),
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    protected function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => ['code' => $code, 'message' => $message],
            'requestId' => bin2hex(random_bytes(8)),
        ], $status);
    }
}

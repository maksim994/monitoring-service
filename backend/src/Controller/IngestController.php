<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Site;
use App\Message\HeartbeatMessage;
use App\Message\MetricsBatchMessage;
use App\Service\Security\ModuleAuthException;
use App\Service\Security\ModuleRequestAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class IngestController extends AbstractController
{
    public function __construct(
        private readonly ModuleRequestAuthenticator $moduleRequestAuthenticator,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/sites/handshake', name: 'ingest_handshake', methods: ['POST'])]
    public function handshake(Request $request): JsonResponse
    {
        try {
            $site = $this->moduleRequestAuthenticator->authenticate($request, $request->getContent());
            $payload = json_decode($request->getContent(), true) ?? [];

            $site->setBitrixVersion($payload['bitrixVersion'] ?? null);
            $site->setPhpVersion($payload['phpVersion'] ?? null);
            $site->setModuleVersion($request->headers->get('X-Module-Version'));
            $site->setStatus(Site::STATUS_OK);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'ok',
                'siteId' => (string) $site->getId(),
                'serverTime' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'configVersion' => $site->getConfigVersion(),
            ]);
        } catch (ModuleAuthException $exception) {
            return $this->moduleError($exception, $request);
        }
    }

    #[Route('/heartbeat', name: 'ingest_heartbeat', methods: ['POST'])]
    public function heartbeat(Request $request): JsonResponse
    {
        try {
            $site = $this->moduleRequestAuthenticator->authenticate($request, $request->getContent());
            $payload = json_decode($request->getContent(), true) ?? [];

            $this->messageBus->dispatch(new HeartbeatMessage(
                (string) $site->getId(),
                (string) $request->headers->get('X-Request-Id'),
                $payload,
            ));

            return $this->accepted($request);
        } catch (ModuleAuthException $exception) {
            return $this->moduleError($exception, $request);
        }
    }

    #[Route('/metrics/batch', name: 'ingest_metrics_batch', methods: ['POST'])]
    public function metricsBatch(Request $request): JsonResponse
    {
        try {
            $site = $this->moduleRequestAuthenticator->authenticate($request, $request->getContent());
            $payload = json_decode($request->getContent(), true) ?? [];

            $this->messageBus->dispatch(new MetricsBatchMessage(
                (string) $site->getId(),
                (string) $request->headers->get('X-Request-Id'),
                $payload,
            ));

            return $this->accepted($request);
        } catch (ModuleAuthException $exception) {
            return $this->moduleError($exception, $request);
        }
    }

    #[Route('/events/batch', name: 'ingest_events_batch', methods: ['POST'])]
    public function eventsBatch(Request $request): JsonResponse
    {
        try {
            $this->moduleRequestAuthenticator->authenticate($request, $request->getContent());

            return $this->accepted($request);
        } catch (ModuleAuthException $exception) {
            return $this->moduleError($exception, $request);
        }
    }

    #[Route('/module/config', name: 'module_config', methods: ['GET'])]
    public function moduleConfig(Request $request): JsonResponse
    {
        try {
            $site = $this->moduleRequestAuthenticator->authenticate($request);

            return $this->json([
                'configVersion' => $site->getConfigVersion(),
                'collectorInterval' => 300,
                'enabledCollectors' => ['environment', 'disk', 'backup', 'modules', 'agents'],
                'limits' => [
                    'maxDirectoryScanDepth' => 3,
                    'maxPayloadBytes' => 262144,
                    'collectorTimeoutSeconds' => 5,
                ],
            ]);
        } catch (ModuleAuthException $exception) {
            return $this->moduleError($exception, $request);
        }
    }

    private function accepted(Request $request): JsonResponse
    {
        return $this->json([
            'status' => 'accepted',
            'requestId' => (string) $request->headers->get('X-Request-Id'),
        ], Response::HTTP_ACCEPTED);
    }

    private function moduleError(ModuleAuthException $exception, Request $request): JsonResponse
    {
        return $this->json([
            'error' => [
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ],
            'requestId' => (string) $request->headers->get('X-Request-Id', bin2hex(random_bytes(8))),
        ], Response::HTTP_UNAUTHORIZED);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Check;
use App\Repository\CheckRepository;
use App\Repository\OrganizationUserRepository;
use App\Service\Probe\ProbeRunner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/internal')]
final class InternalProbeController extends AbstractController
{
    public function __construct(
        private readonly CheckRepository $checkRepository,
        private readonly ProbeRunner $probeRunner,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(INTERNAL_API_TOKEN)%')]
        private readonly string $internalApiToken,
    ) {
    }

    #[Route('/probe-jobs', name: 'internal_probe_jobs', methods: ['GET'])]
    public function jobs(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $jobs = [];
        foreach ($this->checkRepository->findEnabledForProbes() as $check) {
            $jobs[] = [
                'checkId' => (string) $check->getId(),
                'siteId' => (string) $check->getSite()->getId(),
                'domain' => $check->getSite()->getDomain(),
                'type' => $check->getType(),
                'url' => $check->getTargetUrl() ?? $check->getSite()->getSiteUrl(),
                'settings' => $check->getSettingsJson(),
            ];
        }

        return $this->json(['items' => $jobs]);
    }

    #[Route('/probe-results', name: 'internal_probe_results', methods: ['POST'])]
    public function results(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $checkId = (string) ($data['checkId'] ?? '');
        $probeId = isset($data['probeId']) ? (string) $data['probeId'] : null;

        $check = $this->checkRepository->find(Uuid::fromString($checkId));
        if (!$check instanceof Check) {
            return $this->json(['error' => 'Check not found'], Response::HTTP_NOT_FOUND);
        }

        $status = (string) ($data['status'] ?? '');
        $valueJson = is_array($data['valueJson'] ?? null) ? $data['valueJson'] : null;

        if ($valueJson !== null && $status !== '') {
            $result = $this->probeRunner->recordResult($check, $status, $valueJson, $probeId);
        } else {
            $result = $this->probeRunner->runCheck($check, $probeId);
        }
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $this->json(['status' => 'accepted', 'resultId' => (string) $result->getId()], Response::HTTP_ACCEPTED);
    }

    private function isAuthorized(Request $request): bool
    {
        $token = (string) $request->headers->get('X-Internal-Token', '');

        return $this->internalApiToken !== '' && hash_equals($this->internalApiToken, $token);
    }
}

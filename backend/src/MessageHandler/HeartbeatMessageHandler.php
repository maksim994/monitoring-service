<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Site;
use App\Message\HeartbeatMessage;
use App\Repository\SiteRepository;
use App\Service\Alert\AlertEngine;
use App\Service\Check\CheckSnapshotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class HeartbeatMessageHandler
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly AlertEngine $alertEngine,
        private readonly CheckSnapshotService $checkSnapshotService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(HeartbeatMessage $message): void
    {
        $site = $this->siteRepository->find(Uuid::fromString($message->siteId));
        if (!$site instanceof Site) {
            return;
        }

        $module = $message->payload['module'] ?? [];
        $environment = $message->payload['environment'] ?? [];

        $site->recordHeartbeat(
            $module['version'] ?? null,
            $environment['bitrixVersion'] ?? null,
            $environment['phpVersion'] ?? null,
        );

        $this->alertEngine->onHeartbeatReceived($site);
        $this->checkSnapshotService->recordHeartbeat($site);

        $this->entityManager->flush();
    }
}

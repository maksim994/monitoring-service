<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\MetricsBatchMessage;
use App\Repository\SiteRepository;
use App\Service\Alert\AlertEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class MetricsBatchMessageHandler
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly AlertEngine $alertEngine,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(MetricsBatchMessage $message): void
    {
        $site = $this->siteRepository->find(Uuid::fromString($message->siteId));
        if ($site === null) {
            return;
        }

        $metrics = $message->payload['metrics'] ?? [];
        if (!is_array($metrics)) {
            return;
        }

        foreach ($metrics as $metric) {
            if (!is_array($metric)) {
                continue;
            }

            $key = $metric['key'] ?? null;
            if ($key !== 'disk.free_percent') {
                continue;
            }

            $value = $metric['value'] ?? null;
            if (!is_numeric($value)) {
                continue;
            }

            $this->alertEngine->onDiskMetric($site, (float) $value, $metric);
        }

        $this->entityManager->flush();
    }
}

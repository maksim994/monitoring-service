<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\NotificationChannel;
use App\Service\Alert\MaintenanceWindowService;
use App\Repository\IncidentRepository;
use App\Repository\NotificationChannelRepository;
use App\Repository\NotificationDeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final class CriticalIncidentReminderService
{
    public function __construct(
        private readonly IncidentRepository $incidentRepository,
        private readonly NotificationChannelRepository $channelRepository,
        private readonly NotificationDeliveryRepository $deliveryRepository,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly MaintenanceWindowService $maintenanceWindowService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%env(int:CRITICAL_TELEGRAM_REMINDER_SECONDS)%')]
        private readonly int $reminderIntervalSeconds,
    ) {
    }

    public function dispatchDueReminders(): int
    {
        if ($this->reminderIntervalSeconds <= 0) {
            return 0;
        }

        $sent = 0;
        $now = new \DateTimeImmutable();

        foreach ($this->incidentRepository->findActiveCritical() as $incident) {
            if (!$incident->isEligibleForCriticalReminder()) {
                continue;
            }

            if ($this->maintenanceWindowService->suppressesNewIncidents($incident->getSite(), $incident->getCheckType())) {
                continue;
            }

            foreach ($this->channelRepository->findEnabledByOrganization($incident->getOrganization()) as $channel) {
                if (!$this->isReminderDue($incident->getId(), $channel, $incident->getOpenedAt(), $now)) {
                    continue;
                }

                if ($this->notificationDispatcher->dispatchCriticalTelegramReminder($incident, $channel)) {
                    ++$sent;
                }
            }
        }

        $this->entityManager->flush();

        return $sent;
    }

    private function isReminderDue(
        Uuid $incidentId,
        NotificationChannel $channel,
        \DateTimeImmutable $openedAt,
        \DateTimeImmutable $now,
    ): bool {
        $lastSent = $this->deliveryRepository->findLastSuccessfulSentAt($incidentId, $channel);
        $reference = $lastSent ?? $openedAt;

        return ($now->getTimestamp() - $reference->getTimestamp()) >= $this->reminderIntervalSeconds;
    }
}

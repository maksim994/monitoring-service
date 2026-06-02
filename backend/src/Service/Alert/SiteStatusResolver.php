<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Entity\Incident;
use App\Entity\IncidentEvent;
use App\Entity\Site;
use App\Repository\IncidentRepository;

final class SiteStatusResolver
{
    public function __construct(
        private readonly IncidentRepository $incidentRepository,
    ) {
    }

    public function sync(Site $site): void
    {
        if ($site->getStatus() === Site::STATUS_DISABLED) {
            return;
        }

        $activeIncidents = $this->incidentRepository->findActiveBySite($site);
        if ($activeIncidents === []) {
            if ($site->getLastHeartbeatAt() !== null) {
                $site->setStatus(Site::STATUS_OK);
            } elseif ($site->getStatus() !== Site::STATUS_PENDING) {
                $site->setStatus(Site::STATUS_PENDING);
            }

            return;
        }

        $hasCritical = false;
        $hasWarning = false;

        foreach ($activeIncidents as $incident) {
            if ($incident->getSeverity() === Incident::SEVERITY_CRITICAL) {
                $hasCritical = true;
            }
            if ($incident->getSeverity() === Incident::SEVERITY_WARNING) {
                $hasWarning = true;
            }
        }

        if ($hasCritical) {
            $site->setStatus(Site::STATUS_CRITICAL);

            return;
        }

        if ($hasWarning) {
            $site->setStatus(Site::STATUS_WARNING);

            return;
        }

        $site->setStatus(Site::STATUS_OK);
    }
}

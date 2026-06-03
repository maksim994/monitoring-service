<?php

declare(strict_types=1);

namespace App\Service\Alert;

use App\Entity\Site;
use App\Repository\MaintenanceWindowRepository;

final class MaintenanceWindowService
{
    public function __construct(
        private readonly MaintenanceWindowRepository $maintenanceWindowRepository,
    ) {
    }

    public function suppressesNewIncidents(Site $site, string $checkType): bool
    {
        return $this->maintenanceWindowRepository->isSuppressed($site, $checkType);
    }
}

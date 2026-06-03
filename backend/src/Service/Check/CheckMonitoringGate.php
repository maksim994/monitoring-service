<?php

declare(strict_types=1);

namespace App\Service\Check;

use App\Entity\Check;
use App\Entity\Site;
use App\Repository\CheckRepository;

final class CheckMonitoringGate
{
    public function __construct(
        private readonly CheckRepository $checkRepository,
    ) {
    }

    public function isEnabled(Site $site, string $checkType): bool
    {
        $check = $this->checkRepository->findBySiteAndType($site, $checkType);

        if ($check === null) {
            return true;
        }

        return $check->isEnabled();
    }

    public function findCheck(Site $site, string $checkType): ?Check
    {
        return $this->checkRepository->findBySiteAndType($site, $checkType);
    }
}

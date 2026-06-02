<?php

declare(strict_types=1);

namespace App\Service\Check;

use App\Entity\Check;
use App\Entity\Site;
use App\Service\Billing\PlanLimitService;
use Doctrine\ORM\EntityManagerInterface;

final class CheckProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    public function provisionForSite(Site $site): void
    {
        $organization = $site->getOrganization();
        $url = $site->getSiteUrl();
        $uptimeInterval = $this->planLimitService->getUptimeIntervalSeconds($organization);

        $checks = [
            new Check($organization, $site, Check::TYPE_UPTIME_HTTP, $uptimeInterval, [
                'url' => $url,
                'method' => 'GET',
                'expectedStatusMin' => 200,
                'expectedStatusMax' => 399,
            ]),
            new Check($organization, $site, Check::TYPE_SSL_EXPIRY, 43200, [
                'url' => $url,
                'warningDays' => 14,
                'criticalDays' => 3,
            ]),
            new Check($organization, $site, Check::TYPE_DISK_LOW, 300, [
                'warningPercent' => 15,
                'criticalPercent' => 5,
            ]),
        ];

        foreach ($checks as $check) {
            $this->entityManager->persist($check);
        }
    }
}

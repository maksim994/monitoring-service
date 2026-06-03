<?php

declare(strict_types=1);

namespace App\Service\Check;

use App\Entity\Check;
use App\Entity\Site;
use App\Repository\CheckRepository;
use App\Service\Billing\PlanLimitService;
use Doctrine\ORM\EntityManagerInterface;

final class CheckProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CheckRepository $checkRepository,
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    public function provisionForSite(Site $site): void
    {
        foreach ($this->buildDefaultChecks($site) as $check) {
            $this->entityManager->persist($check);
        }
    }

    public function provisionMissingForSite(Site $site): int
    {
        $created = 0;

        foreach ($this->buildDefaultChecks($site) as $check) {
            if ($this->checkRepository->findBySiteAndType($site, $check->getType()) !== null) {
                continue;
            }

            $this->entityManager->persist($check);
            ++$created;
        }

        return $created;
    }

    /** @return list<Check> */
    private function buildDefaultChecks(Site $site): array
    {
        $organization = $site->getOrganization();
        $url = $site->getSiteUrl();
        $domain = $site->getDomain();
        $uptimeInterval = $this->planLimitService->getUptimeIntervalSeconds($organization);

        return [
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
            new Check($organization, $site, Check::TYPE_DOMAIN_EXPIRY, 86400, [
                'domain' => $domain,
                'warningDays' => 30,
                'criticalDays' => 7,
            ]),
            new Check($organization, $site, Check::TYPE_DISK_LOW, 300, [
                'warningPercent' => 15,
                'criticalPercent' => 5,
            ]),
            new Check($organization, $site, Check::TYPE_BACKUP_STALE, 3600, [
                'warningHours' => 72,
                'criticalHours' => 168,
            ]),
            new Check($organization, $site, Check::TYPE_AGENTS_LAG, 300, [
                'warningLagSeconds' => 1800,
                'criticalLagSeconds' => 7200,
            ]),
            new Check($organization, $site, Check::TYPE_MODULES_UPDATES, 43200, [
                'warningUpdatesCount' => 1,
            ]),
        ];
    }
}

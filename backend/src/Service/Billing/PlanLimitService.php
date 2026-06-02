<?php

declare(strict_types=1);

namespace App\Service\Billing;

use App\Entity\Organization;
use App\Entity\NotificationChannel;
use App\Entity\Plan;
use App\Repository\NotificationChannelRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\PlanRepository;
use App\Repository\SiteRepository;

final class PlanLimitService
{
    /** @var array{maxSites: int, minUptimeIntervalSeconds: int, maxUsers: int, webhooksEnabled: bool, label: string} */
    private const FALLBACK_CONFIG = [
        'maxSites' => 1,
        'minUptimeIntervalSeconds' => 900,
        'maxUsers' => 1,
        'webhooksEnabled' => false,
        'label' => 'Free',
    ];

    public function __construct(
        private readonly PlanRepository $planRepository,
        private readonly SiteRepository $siteRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly NotificationChannelRepository $notificationChannelRepository,
    ) {
    }

    /** @return array{maxSites: int, minUptimeIntervalSeconds: int, maxUsers: int, webhooksEnabled: bool, label: string} */
    public function getPlanConfig(Organization $organization): array
    {
        return $this->getConfigByCode($organization->getPlanCode());
    }

    /** @return array{maxSites: int, minUptimeIntervalSeconds: int, maxUsers: int, webhooksEnabled: bool, label: string} */
    public function getConfigByCode(string $planCode): array
    {
        $plan = $this->planRepository->findOneByCode($planCode);
        if ($plan !== null && $plan->isActive()) {
            return $plan->toConfigArray();
        }

        $fallback = $this->planRepository->findOneByCode(Organization::PLAN_FREE);

        return $fallback?->toConfigArray() ?? self::FALLBACK_CONFIG;
    }

    /** @return list<array{code: string, label: string, maxSites: int, maxUsers: int, uptimeIntervalSeconds: int, webhooksEnabled: bool, active: bool, sortOrder: int}> */
    public function getAvailablePlans(bool $includeInactive = false): array
    {
        $plans = $includeInactive
            ? $this->planRepository->findAllOrdered()
            : $this->planRepository->findAllActive();

        return array_map(static fn (Plan $plan) => [
            'code' => $plan->getCode(),
            'label' => $plan->getLabel(),
            'maxSites' => $plan->getMaxSites(),
            'maxUsers' => $plan->getMaxUsers(),
            'uptimeIntervalSeconds' => $plan->getMinUptimeIntervalSeconds(),
            'webhooksEnabled' => $plan->isWebhooksEnabled(),
            'active' => $plan->isActive(),
            'sortOrder' => $plan->getSortOrder(),
        ], $plans);
    }

    /** @return array<string, mixed> */
    public function getUsage(Organization $organization): array
    {
        $config = $this->getPlanConfig($organization);
        $sites = $this->siteRepository->countActiveByOrganization($organization);
        $users = $this->organizationUserRepository->countByOrganization($organization);
        $webhooks = count(array_filter(
            $this->notificationChannelRepository->findByOrganization($organization),
            static fn ($channel) => $channel->getType() === NotificationChannel::TYPE_WEBHOOK,
        ));

        return [
            'planCode' => $organization->getPlanCode(),
            'planLabel' => $config['label'],
            'sites' => ['used' => $sites, 'limit' => $config['maxSites']],
            'users' => ['used' => $users, 'limit' => $config['maxUsers']],
            'uptimeIntervalSeconds' => $config['minUptimeIntervalSeconds'],
            'webhooksEnabled' => $config['webhooksEnabled'],
            'webhooks' => ['used' => $webhooks],
            'availablePlans' => $this->getAvailablePlans(),
        ];
    }

    public function assertCanCreateSite(Organization $organization): void
    {
        $config = $this->getPlanConfig($organization);
        $current = $this->siteRepository->countActiveByOrganization($organization);

        if ($current >= $config['maxSites']) {
            throw new PlanLimitExceededException(sprintf(
                'Site limit reached for plan %s (%d/%d).',
                $config['label'],
                $current,
                $config['maxSites'],
            ));
        }
    }

    public function assertCanCreateChannel(Organization $organization, string $type): void
    {
        if ($type !== NotificationChannel::TYPE_WEBHOOK) {
            return;
        }

        $config = $this->getPlanConfig($organization);
        if (!$config['webhooksEnabled']) {
            throw new PlanLimitExceededException('Webhooks are not available on the current plan.');
        }
    }

    public function assertCanAddUser(Organization $organization): void
    {
        $config = $this->getPlanConfig($organization);
        $current = $this->organizationUserRepository->countByOrganization($organization);

        if ($current >= $config['maxUsers']) {
            throw new PlanLimitExceededException(sprintf(
                'User limit reached for plan %s (%d/%d).',
                $config['label'],
                $current,
                $config['maxUsers'],
            ));
        }
    }

    public function assertCanChangePlan(Organization $organization, string $planCode): void
    {
        if ($this->planRepository->findOneByCode($planCode) === null) {
            throw new \InvalidArgumentException('Invalid plan code.');
        }

        $config = $this->getConfigByCode($planCode);
        $sites = $this->siteRepository->countActiveByOrganization($organization);
        $users = $this->organizationUserRepository->countByOrganization($organization);
        $webhooks = count(array_filter(
            $this->notificationChannelRepository->findByOrganization($organization),
            static fn ($channel) => $channel->getType() === NotificationChannel::TYPE_WEBHOOK,
        ));

        if ($sites > $config['maxSites']) {
            throw new PlanLimitExceededException(sprintf(
                'Cannot switch to %s: active sites %d exceed limit %d.',
                $config['label'],
                $sites,
                $config['maxSites'],
            ));
        }

        if ($users > $config['maxUsers']) {
            throw new PlanLimitExceededException(sprintf(
                'Cannot switch to %s: users %d exceed limit %d.',
                $config['label'],
                $users,
                $config['maxUsers'],
            ));
        }

        if (!$config['webhooksEnabled'] && $webhooks > 0) {
            throw new PlanLimitExceededException(sprintf(
                'Cannot switch to %s: remove webhook channels first.',
                $config['label'],
            ));
        }
    }

    public function getUptimeIntervalSeconds(Organization $organization): int
    {
        return $this->getPlanConfig($organization)['minUptimeIntervalSeconds'];
    }
}

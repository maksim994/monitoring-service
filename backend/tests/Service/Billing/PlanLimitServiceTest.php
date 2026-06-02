<?php

declare(strict_types=1);

namespace App\Tests\Service\Billing;

use App\Entity\Organization;
use App\Entity\Plan;
use App\Entity\Site;
use App\Repository\NotificationChannelRepository;
use App\Repository\OrganizationUserRepository;
use App\Repository\PlanRepository;
use App\Repository\SiteRepository;
use App\Service\Billing\PlanLimitExceededException;
use App\Service\Billing\PlanLimitService;
use PHPUnit\Framework\TestCase;

final class PlanLimitServiceTest extends TestCase
{
    public function testBlocksSiteCreationWhenLimitReached(): void
    {
        $organization = new Organization('Org');
        $siteRepository = $this->createMock(SiteRepository::class);
        $siteRepository->method('countActiveByOrganization')->willReturn(1);

        $service = $this->createService($siteRepository);

        $this->expectException(PlanLimitExceededException::class);
        $service->assertCanCreateSite($organization);
    }

    public function testBlocksDowngradeWhenSitesExceedTargetPlan(): void
    {
        $organization = new Organization('Org');
        $organization->setPlanCode(Organization::PLAN_AGENCY);

        $siteRepository = $this->createMock(SiteRepository::class);
        $siteRepository->method('countActiveByOrganization')->willReturn(3);

        $userRepository = $this->createMock(OrganizationUserRepository::class);
        $userRepository->method('countByOrganization')->willReturn(1);

        $planRepository = $this->createMock(PlanRepository::class);
        $planRepository->method('findOneByCode')->willReturnCallback(static fn (string $code) => match ($code) {
            Organization::PLAN_FREE => new Plan('free', 'Free', 1, 1, 900, false),
            Organization::PLAN_AGENCY => new Plan('agency', 'Agency', 25, 10, 60, true),
            default => null,
        });

        $service = new PlanLimitService(
            $planRepository,
            $siteRepository,
            $userRepository,
            $this->createMock(NotificationChannelRepository::class),
        );

        $this->expectException(PlanLimitExceededException::class);
        $service->assertCanChangePlan($organization, Organization::PLAN_FREE);
    }

    public function testAllowsUpgradeWhenUsageFitsPlan(): void
    {
        $organization = new Organization('Org');

        $siteRepository = $this->createMock(SiteRepository::class);
        $siteRepository->method('countActiveByOrganization')->willReturn(1);

        $userRepository = $this->createMock(OrganizationUserRepository::class);
        $userRepository->method('countByOrganization')->willReturn(1);

        $service = $this->createService($siteRepository, $userRepository);

        $service->assertCanChangePlan($organization, Organization::PLAN_BASIC);
        $this->addToAssertionCount(1);
    }

    private function createService(
        SiteRepository $siteRepository,
        ?OrganizationUserRepository $userRepository = null,
    ): PlanLimitService {
        $planRepository = $this->createMock(PlanRepository::class);
        $planRepository->method('findOneByCode')->willReturnCallback(static fn (string $code) => match ($code) {
            Organization::PLAN_FREE => new Plan('free', 'Free', 1, 1, 900, false),
            Organization::PLAN_BASIC => new Plan('basic', 'Basic', 5, 3, 300, true),
            default => null,
        });

        return new PlanLimitService(
            $planRepository,
            $siteRepository,
            $userRepository ?? $this->createMock(OrganizationUserRepository::class),
            $this->createMock(NotificationChannelRepository::class),
        );
    }
}

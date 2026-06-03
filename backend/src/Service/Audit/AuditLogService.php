<?php

declare(strict_types=1);

namespace App\Service\Audit;

use App\Entity\AuditLog;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class AuditLogService
{
    public const ACTION_SITE_CREATED = 'site.created';
    public const ACTION_SITE_KEY_ROTATED = 'site.key_rotated';
    public const ACTION_SITE_DISABLED = 'site.disabled';
    public const ACTION_SITE_ENABLED = 'site.enabled';
    public const ACTION_PLAN_CHANGED = 'plan.changed';
    public const ACTION_ORGANIZATION_UPDATED = 'organization.updated';
    public const ACTION_PLAN_UPDATED = 'plan.updated';
    public const ACTION_CHANNEL_CREATED = 'notification_channel.created';
    public const ACTION_CHANNEL_TEST = 'notification_channel.tested';
    public const ACTION_USER_INVITED = 'user.invited';
    public const ACTION_USER_ROLE_UPDATED = 'user.role_updated';
    public const ACTION_USER_REMOVED = 'user.removed';
    public const ACTION_INCIDENT_ACKNOWLEDGED = 'incident.acknowledged';
    public const ACTION_INCIDENT_RESOLVED = 'incident.resolved';
    public const ACTION_MAINTENANCE_CREATED = 'maintenance.created';
    public const ACTION_MAINTENANCE_CANCELLED = 'maintenance.cancelled';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function record(
        Organization $organization,
        ?User $actor,
        string $action,
        string $targetType,
        ?string $targetId,
        string $message,
        array $payload = [],
    ): void {
        $this->entityManager->persist(new AuditLog(
            $organization,
            $actor?->getId(),
            $action,
            $targetType,
            $targetId,
            $message,
            $payload,
        ));
    }
}

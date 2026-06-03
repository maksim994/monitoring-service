<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NotificationChannel;
use App\Entity\User;
use App\Repository\NotificationChannelRepository;
use App\Repository\OrganizationUserRepository;
use App\Service\Audit\AuditLogService;
use App\Service\Billing\PlanLimitExceededException;
use App\Service\Billing\PlanLimitService;
use App\Service\Notification\NotificationDispatcher;
use App\Service\Notification\SmtpErrorMessageMapper;
use App\Service\Notification\WebhookUrlValidator;
use App\Service\Security\AccessDeniedException;
use App\Service\Security\OrganizationAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/notification-channels')]
final class NotificationChannelController extends AbstractController
{
    public function __construct(
        private readonly NotificationChannelRepository $channelRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly PlanLimitService $planLimitService,
        private readonly OrganizationAccessService $organizationAccessService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly WebhookUrlValidator $webhookUrlValidator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'notification_channels_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $channels = $this->channelRepository->findByOrganization($organization);

        return $this->json([
            'items' => array_map(fn (NotificationChannel $channel) => $this->serialize($channel), $channels),
        ]);
    }

    #[Route('', name: 'notification_channels_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_MANAGE_CHANNELS);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        if ($organization === null) {
            return $this->error('organization_not_found', 'Organization was not found.', Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $type = (string) ($data['type'] ?? NotificationChannel::TYPE_EMAIL);
        $name = trim((string) ($data['name'] ?? ''));
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        if ($name === '') {
            return $this->error('validation_failed', 'name is required.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->planLimitService->assertCanCreateChannel($organization, $type);
            $settings = $this->normalizeSettings($type, $settings);
        } catch (PlanLimitExceededException $exception) {
            return $this->error('plan_limit_exceeded', $exception->getMessage(), Response::HTTP_PAYMENT_REQUIRED);
        } catch (\InvalidArgumentException $exception) {
            return $this->error('validation_failed', $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $channel = new NotificationChannel($organization, $type, $name, $settings);
        $this->entityManager->persist($channel);

        $this->auditLogService->record(
            $organization,
            $user,
            AuditLogService::ACTION_CHANNEL_CREATED,
            'notification_channel',
            (string) $channel->getId(),
            sprintf('Notification channel %s (%s) created', $name, $type),
            ['type' => $type, 'name' => $name],
        );

        $this->entityManager->flush();

        return $this->json($this->serialize($channel), Response::HTTP_CREATED);
    }

    #[Route('/{channelId}/test', name: 'notification_channels_test', methods: ['POST'])]
    public function test(string $channelId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->organizationAccessService->assertCan($user, OrganizationAccessService::PERM_MANAGE_CHANNELS);
        } catch (AccessDeniedException $exception) {
            return $this->error('access_denied', $exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $organization = $this->getCurrentOrganization();
        $channel = $this->channelRepository->find(Uuid::fromString($channelId));

        if (!$channel instanceof NotificationChannel || $organization === null || !$channel->getOrganization()->getId()->equals($organization->getId())) {
            return $this->error('channel_not_found', 'Notification channel was not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->notificationDispatcher->sendTest($channel);
        } catch (\Throwable $exception) {
            return $this->error(
                'delivery_failed',
                SmtpErrorMessageMapper::toUserMessage($exception),
                Response::HTTP_BAD_GATEWAY,
            );
        }

        $this->auditLogService->record(
            $organization,
            $user,
            AuditLogService::ACTION_CHANNEL_TEST,
            'notification_channel',
            (string) $channel->getId(),
            sprintf('Test notification sent for channel %s', $channel->getName()),
            ['type' => $channel->getType()],
        );
        $this->entityManager->flush();

        return $this->json(['status' => 'sent']);
    }

    /** @param array<string, mixed> $settings */
    private function normalizeSettings(string $type, array $settings): array
    {
        return match ($type) {
            NotificationChannel::TYPE_EMAIL => $this->normalizeEmailSettings($settings),
            NotificationChannel::TYPE_TELEGRAM => $this->normalizeTelegramSettings($settings),
            NotificationChannel::TYPE_WEBHOOK => $this->normalizeWebhookSettings($settings),
            default => throw new \InvalidArgumentException('Unsupported channel type.'),
        };
    }

    /** @param array<string, mixed> $settings */
    private function normalizeEmailSettings(array $settings): array
    {
        $email = trim((string) ($settings['email'] ?? ''));
        if ($email === '') {
            throw new \InvalidArgumentException('settings.email is required for email channel.');
        }

        return ['email' => $email];
    }

    /** @param array<string, mixed> $settings */
    private function normalizeTelegramSettings(array $settings): array
    {
        $chatId = trim((string) ($settings['chatId'] ?? ''));
        if ($chatId === '') {
            throw new \InvalidArgumentException('settings.chatId is required for telegram channel.');
        }

        $normalized = ['chatId' => $chatId];
        $botToken = trim((string) ($settings['botToken'] ?? ''));
        if ($botToken !== '') {
            $normalized['botToken'] = $botToken;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $settings */
    private function normalizeWebhookSettings(array $settings): array
    {
        $url = trim((string) ($settings['url'] ?? ''));
        if ($url === '') {
            throw new \InvalidArgumentException('settings.url is required for webhook channel.');
        }

        $this->webhookUrlValidator->assertSafe($url);
        $normalized = ['url' => $url];
        $secret = trim((string) ($settings['secret'] ?? ''));
        if ($secret !== '') {
            $normalized['secret'] = $secret;
        }

        return $normalized;
    }

    private function getCurrentOrganization()
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId())?->getOrganization();
    }

    private function serialize(NotificationChannel $channel): array
    {
        return [
            'id' => (string) $channel->getId(),
            'type' => $channel->getType(),
            'name' => $channel->getName(),
            'settings' => $this->serializeSettingsForApi($channel),
            'enabled' => $channel->isEnabled(),
            'createdAt' => $channel->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeSettingsForApi(NotificationChannel $channel): array
    {
        $settings = $channel->getSettingsJson();
        if ($channel->getType() !== NotificationChannel::TYPE_TELEGRAM) {
            return $settings;
        }

        if (isset($settings['botToken']) && is_string($settings['botToken']) && $settings['botToken'] !== '') {
            $settings['botTokenConfigured'] = true;
            unset($settings['botToken']);
        }

        return $settings;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => ['code' => $code, 'message' => $message],
            'requestId' => bin2hex(random_bytes(8)),
        ], $status);
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Incident;
use App\Entity\NotificationChannel;
use App\Service\Alert\DiskEvidenceHelper;
use App\Entity\NotificationDelivery;
use App\Repository\NotificationChannelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationChannelRepository $channelRepository,
        private readonly MailerInterface $mailer,
        private readonly MailerFromResolver $mailerFromResolver,
        private readonly WebhookUrlValidator $webhookUrlValidator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::TELEGRAM_BOT_TOKEN)%')]
        private readonly ?string $telegramBotToken,
        #[Autowire('%env(default::TELEGRAM_PROXY_URL)%')]
        private readonly ?string $telegramProxyUrl,
        #[Autowire('%env(default::FRONTEND_URL)%')]
        private readonly string $frontendUrl,
    ) {
    }

    public function dispatchIncidentOpened(Incident $incident): void
    {
        $channels = $this->channelRepository->findEnabledByOrganization($incident->getOrganization());

        foreach ($channels as $channel) {
            $this->sendToChannel($channel, $this->buildIncidentPayload($incident, 'incident.opened'), $incident, false);
        }

        $this->entityManager->flush();
    }

    public function dispatchCriticalTelegramReminder(Incident $incident, NotificationChannel $channel): bool
    {
        if ($channel->getType() !== NotificationChannel::TYPE_TELEGRAM) {
            return false;
        }

        if (!$incident->isEligibleForCriticalReminder()) {
            return false;
        }

        return $this->sendToChannel(
            $channel,
            $this->buildIncidentPayload($incident, 'incident.critical_reminder', true),
            $incident,
            false,
        );
    }

    public function sendTest(NotificationChannel $channel): void
    {
        $payload = [
            'event' => 'notification.test',
            'severity' => 'info',
            'status' => 'test',
            'checkType' => 'test',
            'title' => 'Тестовое уведомление Monitoring Service',
            'openedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'evidence' => ['message' => 'Канал настроен корректно'],
        ];

        $this->sendToChannel($channel, $payload, null, true);
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $payload */
    private function sendToChannel(NotificationChannel $channel, array $payload, ?Incident $incident = null, bool $throwOnFailure = false): bool
    {
        try {
            match ($channel->getType()) {
                NotificationChannel::TYPE_EMAIL => $this->sendEmail($channel, $payload, $incident),
                NotificationChannel::TYPE_TELEGRAM => $this->sendTelegram($channel, $payload),
                NotificationChannel::TYPE_WEBHOOK => $this->sendWebhook($channel, $payload),
                default => throw new \RuntimeException('Unsupported channel type'),
            };

            $this->entityManager->persist(new NotificationDelivery(
                $channel->getOrganization(),
                $channel,
                NotificationDelivery::STATUS_SENT,
                $incident?->getId(),
            ));

            return true;
        } catch (\Throwable $exception) {
            $this->entityManager->persist(new NotificationDelivery(
                $channel->getOrganization(),
                $channel,
                NotificationDelivery::STATUS_FAILED,
                $incident?->getId(),
                $exception->getMessage(),
            ));

            $this->logger->error('Notification delivery failed', [
                'channelId' => (string) $channel->getId(),
                'incidentId' => $incident !== null ? (string) $incident->getId() : null,
                'error' => $exception->getMessage(),
            ]);

            if ($throwOnFailure) {
                throw $exception;
            }

            return false;
        }
    }

    /** @param array<string, mixed> $payload */
    private function sendEmail(NotificationChannel $channel, array $payload, ?Incident $incident): void
    {
        $to = $channel->getSettingsJson()['email'] ?? null;
        if (!is_string($to) || $to === '') {
            throw new \RuntimeException('Email channel is not configured.');
        }

        $subject = $incident instanceof Incident
            ? sprintf('[Monitoring] %s — %s', strtoupper($incident->getSeverity()), $incident->getTitle())
            : '[Monitoring] Тестовое уведомление';

        $body = $this->formatTextBody($payload);

        $email = (new Email())
            ->from($this->mailerFromResolver->resolve())
            ->to($to)
            ->subject($subject)
            ->text($body);

        $this->mailer->send($email);
    }

    /** @param array<string, mixed> $payload */
    private function sendTelegram(NotificationChannel $channel, array $payload): void
    {
        $botToken = $this->resolveTelegramBotToken($channel);

        $chatId = $channel->getSettingsJson()['chatId'] ?? null;
        if (!is_string($chatId) || $chatId === '') {
            throw new \RuntimeException('Telegram chatId is not configured.');
        }

        $text = $this->formatTextBody($payload);
        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken);
        $body = json_encode(['chat_id' => $chatId, 'text' => $text], JSON_THROW_ON_ERROR);

        $this->postJson($url, $body, ['Content-Type: application/json'], $this->telegramProxyUrl);
    }

    private function resolveTelegramBotToken(NotificationChannel $channel): string
    {
        $channelToken = $channel->getSettingsJson()['botToken'] ?? null;
        if (is_string($channelToken) && $channelToken !== '') {
            return $channelToken;
        }

        if ($this->telegramBotToken !== null && $this->telegramBotToken !== '') {
            return $this->telegramBotToken;
        }

        throw new \RuntimeException('Укажите Bot Token в канале или TELEGRAM_BOT_TOKEN на сервере.');
    }

    /** @param array<string, mixed> $payload */
    private function sendWebhook(NotificationChannel $channel, array $payload): void
    {
        $url = $channel->getSettingsJson()['url'] ?? null;
        if (!is_string($url) || $url === '') {
            throw new \RuntimeException('Webhook URL is not configured.');
        }

        $this->webhookUrlValidator->assertSafe($url);
        $body = json_encode(['event' => $payload['event'] ?? 'incident.opened', ...$payload], JSON_THROW_ON_ERROR);
        $headers = ['Content-Type: application/json', 'User-Agent: MonitoringService/0.1.0'];

        $secret = $channel->getSettingsJson()['secret'] ?? null;
        if (is_string($secret) && $secret !== '') {
            $headers[] = 'X-Monitoring-Signature: sha256='.hash_hmac('sha256', $body, $secret);
        }

        $this->postJson($url, $body, $headers, null);
    }

    /** @return array<string, mixed> */
    private function buildIncidentPayload(
        Incident $incident,
        string $event = 'incident.opened',
        bool $isReminder = false,
    ): array {
        return [
            'event' => $event,
            'incidentId' => (string) $incident->getId(),
            'siteId' => (string) $incident->getSite()->getId(),
            'siteDomain' => $incident->getSite()->getDomain(),
            'severity' => $incident->getSeverity(),
            'status' => $incident->getStatus(),
            'checkType' => $incident->getCheckType(),
            'title' => $incident->getTitle(),
            'openedAt' => $incident->getOpenedAt()->format(DATE_ATOM),
            'evidence' => $incident->getLastEvidenceJson(),
            'isReminder' => $isReminder,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function formatTextBody(array $payload): string
    {
        $lines = [];
        if (!empty($payload['isReminder'])) {
            $lines[] = 'Повторное напоминание: инцидент всё ещё открыт';
            $lines[] = '';
        }

        $lines[] = $payload['title'] ?? 'Monitoring notification';
        $lines[] = '';
            'Severity: '.($payload['severity'] ?? 'info'),
            'Check: '.($payload['checkType'] ?? 'unknown'),
        ];

        if (isset($payload['siteDomain'])) {
            $lines[] = 'Site: '.$payload['siteDomain'];
        }

        if (isset($payload['openedAt'])) {
            $lines[] = 'Opened: '.$payload['openedAt'];
        }

        $evidence = $payload['evidence'] ?? null;
        if (is_array($evidence) && ($payload['checkType'] ?? null) === 'disk_low') {
            $diskLines = $this->formatDiskEvidenceLines($evidence);
            if ($diskLines !== []) {
                $lines[] = '';
                $lines = [...$lines, ...$diskLines];
            }
        }

        $lines[] = '';
        $lines[] = 'Кабинет: '.$this->frontendUrl.'/incidents';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $evidence */
    private function formatDiskEvidenceLines(array $evidence): array
    {
        $lines = [];
        $path = $evidence['path'] ?? null;
        if (is_string($path) && $path !== '') {
            $lines[] = 'Путь: '.$path;
        }

        $totalBytes = $evidence['totalBytes'] ?? null;
        $freeBytes = $evidence['freeBytes'] ?? null;
        $usedBytes = $evidence['usedBytes'] ?? null;
        if (is_int($totalBytes) && is_int($freeBytes) && is_int($usedBytes)) {
            $lines[] = sprintf(
                'Диск: свободно %s, занято %s, всего %s',
                DiskEvidenceHelper::formatBytes($freeBytes),
                DiskEvidenceHelper::formatBytes($usedBytes),
                DiskEvidenceHelper::formatBytes($totalBytes),
            );
        }

        return $lines;
    }

    /** @param list<string> $headers */
    private function postJson(string $url, string $body, array $headers, ?string $proxyUrl): void
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 10,
        ];

        if (is_string($proxyUrl) && $proxyUrl !== '') {
            $options[CURLOPT_PROXY] = $proxyUrl;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            throw new \RuntimeException(sprintf('HTTP request failed with status %d: %s', $status, (string) $response));
        }
    }
}

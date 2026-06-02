<?php

declare(strict_types=1);

namespace Vendor\Monitoring\Application\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Vendor\Monitoring\Infrastructure\Queue\ModuleQueue;

final class ModuleSender
{
    private const MODULE_ID = 'vendor.monitoring';

    public function sendHeartbeat(array $environment = []): array
    {
        $payload = [
            'eventId' => $this->uuid(),
            'collectedAt' => gmdate('c'),
            'module' => [
                'version' => '0.1.0',
                'mode' => Option::get(self::MODULE_ID, 'mode', 'saas'),
                'collectorInterval' => 300,
            ],
            'environment' => $environment !== [] ? $environment : [
                'bitrixVersion' => defined('SM_VERSION') ? SM_VERSION : 'unknown',
                'phpVersion' => PHP_VERSION,
            ],
        ];

        return $this->sendSignedPost('/api/v1/heartbeat', $payload, 'heartbeat');
    }

    /** @param list<array<string, mixed>> $metrics */
    public function sendMetricsBatch(array $metrics): array
    {
        $payload = [
            'batchId' => $this->uuid(),
            'collectedAt' => gmdate('c'),
            'metrics' => $metrics,
        ];

        return $this->sendSignedPost('/api/v1/metrics/batch', $payload, 'metrics');
    }

    public function flushQueue(): void
    {
        $queue = new ModuleQueue();
        foreach ($queue->pending() as $item) {
            $type = (string) ($item['type'] ?? '');
            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $path = $type === 'metrics' ? '/api/v1/metrics/batch' : '/api/v1/heartbeat';
            $result = $this->sendSignedPost($path, $payload, $type, false);

            if ($result['success'] ?? false) {
                $queue->markSent((string) $item['id']);
                continue;
            }

            $queue->markFailed((string) $item['id'], (string) ($result['response'] ?? $result['error'] ?? 'unknown error'));
        }
    }

    /** @param array<string, mixed> $payload */
    private function sendSignedPost(string $path, array $payload, string $queueType, bool $useQueueOnFailure = true): array
    {
        $apiUrl = rtrim((string) Option::get(self::MODULE_ID, 'api_url', ''), '/');
        $siteId = (string) Option::get(self::MODULE_ID, 'site_id', '');
        $secret = (string) Option::get(self::MODULE_ID, 'api_secret', '');

        if ($apiUrl === '' || $siteId === '' || $secret === '') {
            return ['success' => false, 'error' => 'Module is not configured'];
        }

        $body = Json::encode($payload);
        $timestamp = gmdate('c');
        $requestId = $this->uuid();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $client = new HttpClient([
            'socketTimeout' => 5,
            'streamTimeout' => 10,
            'disableSslVerification' => false,
            'redirect' => false,
        ]);

        $client->setHeader('Content-Type', 'application/json');
        $client->setHeader('X-Site-Id', $siteId);
        $client->setHeader('X-Timestamp', $timestamp);
        $client->setHeader('X-Signature', $signature);
        $client->setHeader('X-Module-Version', '0.1.0');
        $client->setHeader('X-Request-Id', $requestId);
        $client->setHeader('User-Agent', 'VendorMonitoringModule/0.1.0');

        $result = $client->post($apiUrl.$path, $body);
        $status = (int) $client->getStatus();
        $success = $status >= 200 && $status < 300;

        if (!$success && $useQueueOnFailure) {
            (new ModuleQueue())->enqueue($queueType, $payload);
        }

        return [
            'success' => $success,
            'status' => $status,
            'response' => $result,
        ];
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

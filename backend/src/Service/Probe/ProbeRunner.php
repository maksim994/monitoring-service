<?php

declare(strict_types=1);

namespace App\Service\Probe;

use App\Entity\Check;
use App\Entity\CheckResult;
use App\Repository\CheckResultRepository;
use App\Service\Alert\AlertEngine;

final class ProbeRunner
{
    public function __construct(
        private readonly CheckResultRepository $checkResultRepository,
        private readonly AlertEngine $alertEngine,
    ) {
    }

    public function runCheck(Check $check, ?string $probeId = null): CheckResult
    {
        $previous = $this->checkResultRepository->findLatestForCheck($check);
        $base = match ($check->getType()) {
            Check::TYPE_UPTIME_HTTP => $this->runHttpCheck($check, $probeId),
            Check::TYPE_SSL_EXPIRY => $this->runSslCheck($check, $probeId),
            default => new CheckResult($check, CheckResult::STATUS_UNKNOWN, ['error' => 'Unsupported probe check type'], $probeId),
        };

        return $this->recordResult($check, $base->getStatus(), $base->getValueJson(), $probeId, $previous);
    }

    public function recordResult(Check $check, string $status, array $valueJson, ?string $probeId = null): CheckResult
    {
        $previous = $this->checkResultRepository->findLatestForCheck($check);
        $result = CheckResult::fromProbe($check, $status, $valueJson, $previous, $probeId);
        $this->alertEngine->onProbeResult($check, $result);

        return $result;
    }

    private function runHttpCheck(Check $check, ?string $probeId): CheckResult
    {
        $url = $check->getTargetUrl() ?? $check->getSite()->getSiteUrl();
        $minStatus = (int) ($check->getSettingsJson()['expectedStatusMin'] ?? 200);
        $maxStatus = (int) ($check->getSettingsJson()['expectedStatusMax'] ?? 399);

        $started = microtime(true);
        $ch = curl_init($url);
        if ($ch === false) {
            return new CheckResult($check, CheckResult::STATUS_CRITICAL, [
                'url' => $url,
                'error' => 'curl_init failed',
            ], $probeId);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_NOBODY => false,
            CURLOPT_USERAGENT => 'MonitoringProbe/0.1.0',
        ]);

        curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $responseTimeMs = (int) round((microtime(true) - $started) * 1000);
        $valueJson = [
            'url' => $url,
            'httpStatus' => $httpStatus,
            'responseTimeMs' => $responseTimeMs,
        ];

        if ($error !== '') {
            $valueJson['error'] = $error;

            return new CheckResult($check, CheckResult::STATUS_CRITICAL, $valueJson, $probeId);
        }

        if ($httpStatus < $minStatus || $httpStatus > $maxStatus) {
            $valueJson['expectedStatusMin'] = $minStatus;
            $valueJson['expectedStatusMax'] = $maxStatus;

            return new CheckResult($check, CheckResult::STATUS_CRITICAL, $valueJson, $probeId);
        }

        return new CheckResult($check, CheckResult::STATUS_OK, $valueJson, $probeId);
    }

    private function runSslCheck(Check $check, ?string $probeId): CheckResult
    {
        $url = $check->getTargetUrl() ?? $check->getSite()->getSiteUrl();
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $port = $parts['port'] ?? 443;

        if (!is_string($host) || $host === '') {
            return new CheckResult($check, CheckResult::STATUS_UNKNOWN, [
                'url' => $url,
                'error' => 'Invalid URL host',
            ], $probeId);
        }

        $warningDays = (int) ($check->getSettingsJson()['warningDays'] ?? 14);
        $criticalDays = (int) ($check->getSettingsJson()['criticalDays'] ?? 3);

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $client = @stream_socket_client(
            sprintf('ssl://%s:%d', $host, $port),
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return new CheckResult($check, CheckResult::STATUS_CRITICAL, [
                'host' => $host,
                'error' => $errstr !== '' ? $errstr : 'SSL handshake failed',
                'errno' => $errno,
            ], $probeId);
        }

        fclose($client);
        $params = stream_context_get_params($context);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (!is_resource($cert) && !is_string($cert)) {
            return new CheckResult($check, CheckResult::STATUS_UNKNOWN, [
                'host' => $host,
                'error' => 'Certificate not captured',
            ], $probeId);
        }

        $parsed = openssl_x509_parse($cert);
        $validTo = $parsed['validTo_time_t'] ?? null;
        if (!is_int($validTo)) {
            return new CheckResult($check, CheckResult::STATUS_UNKNOWN, [
                'host' => $host,
                'error' => 'Certificate expiry unavailable',
            ], $probeId);
        }

        $daysLeft = (int) floor(($validTo - time()) / 86400);
        $valueJson = [
            'host' => $host,
            'daysLeft' => $daysLeft,
            'validTo' => gmdate('c', $validTo),
            'warningDays' => $warningDays,
            'criticalDays' => $criticalDays,
        ];

        if ($daysLeft < $criticalDays) {
            return new CheckResult($check, CheckResult::STATUS_CRITICAL, $valueJson, $probeId);
        }

        if ($daysLeft < $warningDays) {
            return new CheckResult($check, CheckResult::STATUS_WARNING, $valueJson, $probeId);
        }

        return new CheckResult($check, CheckResult::STATUS_OK, $valueJson, $probeId);
    }
}

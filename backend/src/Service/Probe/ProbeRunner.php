<?php

declare(strict_types=1);

namespace App\Service\Probe;

use App\Entity\Check;
use App\Entity\CheckResult;
use App\Repository\CheckResultRepository;
use App\Service\Alert\AlertEngine;
use App\Service\Check\CheckSnapshotService;

final class ProbeRunner
{
    public function __construct(
        private readonly CheckResultRepository $checkResultRepository,
        private readonly DomainExpiryLookup $domainExpiryLookup,
        private readonly AlertEngine $alertEngine,
        private readonly CheckSnapshotService $checkSnapshotService,
    ) {
    }

    public function runCheck(Check $check, ?string $probeId = null): CheckResult
    {
        $previous = $this->checkResultRepository->findLatestForCheck($check);
        $base = match ($check->getType()) {
            Check::TYPE_UPTIME_HTTP => $this->runHttpCheck($check, $probeId),
            Check::TYPE_SSL_EXPIRY => $this->runSslCheck($check, $probeId),
            Check::TYPE_DOMAIN_EXPIRY => $this->runDomainCheck($check, $probeId),
            default => new CheckResult($check, CheckResult::STATUS_UNKNOWN, ['error' => 'Unsupported probe check type'], $probeId),
        };

        return $this->recordResult($check, $base->getStatus(), $base->getValueJson(), $probeId);
    }

    public function recordResult(Check $check, string $status, array $valueJson, ?string $probeId = null): CheckResult
    {
        $previous = $this->checkResultRepository->findLatestForCheck($check);
        $result = CheckResult::fromProbe($check, $status, $valueJson, $previous, $probeId);
        $this->checkSnapshotService->recordFromProbeResult($result);
        $this->alertEngine->onProbeResult($check, $result);

        return $result;
    }

    private function runHttpCheck(Check $check, ?string $probeId): CheckResult
    {
        $url = $this->normalizeUrl($check->getTargetUrl() ?? $check->getSite()->getSiteUrl());
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
        $url = $this->normalizeUrl($check->getTargetUrl() ?? $check->getSite()->getSiteUrl());
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

        $sslRead = $this->readSslCertificate($host, (int) $port);
        if ($sslRead['error'] !== null) {
            $status = str_contains(strtolower($sslRead['error']), 'handshake') ? CheckResult::STATUS_CRITICAL : CheckResult::STATUS_UNKNOWN;

            return new CheckResult($check, $status, [
                'url' => $url,
                'host' => $host,
                'error' => $sslRead['error'],
                'errno' => $sslRead['errno'] ?? 0,
            ], $probeId);
        }

        $validTo = $sslRead['validTo'];
        if (!is_int($validTo)) {
            return new CheckResult($check, CheckResult::STATUS_UNKNOWN, [
                'url' => $url,
                'host' => $host,
                'error' => 'Certificate expiry unavailable',
            ], $probeId);
        }

        $daysLeft = (int) floor(($validTo - time()) / 86400);
        $valueJson = [
            'url' => $url,
            'host' => $host,
            'daysLeft' => $daysLeft,
            'validTo' => gmdate('c', $validTo),
            'warningDays' => $warningDays,
            'criticalDays' => $criticalDays,
            'sslVerifySkipped' => $sslRead['verifySkipped'],
        ];

        if ($daysLeft < $criticalDays) {
            return new CheckResult($check, CheckResult::STATUS_CRITICAL, $valueJson, $probeId);
        }

        if ($daysLeft < $warningDays) {
            return new CheckResult($check, CheckResult::STATUS_WARNING, $valueJson, $probeId);
        }

        return new CheckResult($check, CheckResult::STATUS_OK, $valueJson, $probeId);
    }

    /**
     * @return array{validTo: ?int, error: ?string, errno?: int, verifySkipped: bool}
     */
    private function readSslCertificate(string $host, int $port): array
    {
        $lastError = 'SSL handshake failed';
        $lastErrno = 0;

        foreach ([true, false] as $verifyPeer) {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => $verifyPeer,
                    'verify_peer_name' => $verifyPeer,
                    'SNI_enabled' => true,
                    'peer_name' => $host,
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
                $lastError = $errstr !== '' ? $errstr : 'SSL handshake failed';
                $lastErrno = $errno;

                continue;
            }

            fclose($client);
            $params = stream_context_get_params($context);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if ($cert === null) {
                $lastError = 'Certificate not captured';
                $lastErrno = 0;

                continue;
            }

            $parsed = openssl_x509_parse($cert);
            $validTo = is_array($parsed) ? ($parsed['validTo_time_t'] ?? null) : null;
            if (is_int($validTo)) {
                return [
                    'validTo' => $validTo,
                    'error' => null,
                    'verifySkipped' => !$verifyPeer,
                ];
            }

            $lastError = 'Certificate parse failed';
        }

        return [
            'validTo' => null,
            'error' => $lastError,
            'errno' => $lastErrno,
            'verifySkipped' => false,
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            return $url;
        }

        return 'https://'.$url;
    }

    private function runDomainCheck(Check $check, ?string $probeId): CheckResult
    {
        $settings = $check->getSettingsJson();
        $domain = is_string($settings['domain'] ?? null) && $settings['domain'] !== ''
            ? $settings['domain']
            : $check->getSite()->getDomain();

        $warningDays = (int) ($settings['warningDays'] ?? 30);
        $criticalDays = (int) ($settings['criticalDays'] ?? 7);

        $lookup = $this->domainExpiryLookup->lookup($domain);
        if (!$lookup->isSuccess()) {
            return new CheckResult($check, CheckResult::STATUS_UNKNOWN, [
                'domain' => $domain,
                'error' => $lookup->error ?? 'Domain expiry lookup failed',
                'source' => $lookup->source,
            ], $probeId);
        }

        $expiresAt = $lookup->expiresAt;
        $daysLeft = $lookup->daysLeft ?? 0;
        $valueJson = [
            'domain' => $domain,
            'daysLeft' => $daysLeft,
            'expiresAt' => $expiresAt?->format(DATE_ATOM),
            'warningDays' => $warningDays,
            'criticalDays' => $criticalDays,
            'source' => $lookup->source,
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

#!/usr/bin/env php
<?php

declare(strict_types=1);

$apiUrl = rtrim(getenv('INGEST_API_URL') ?: 'http://api:8080', '/');
$token = getenv('INTERNAL_API_TOKEN') ?: 'dev-internal-token';
$probeId = getenv('PROBE_ID') ?: 'local-1';

function request(string $method, string $url, string $token, ?string $body = null): array
{
    $ch = curl_init($url);
    $headers = [
        'X-Internal-Token: '.$token,
        'Accept: application/json',
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POSTFIELDS => $body,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => is_string($response) ? json_decode($response, true) : null,
    ];
}

function runHttpProbe(array $job): array
{
    $url = (string) ($job['url'] ?? '');
    $settings = is_array($job['settings'] ?? null) ? $job['settings'] : [];
    $minStatus = (int) ($settings['expectedStatusMin'] ?? 200);
    $maxStatus = (int) ($settings['expectedStatusMax'] ?? 399);

    $started = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'MonitoringProbe/0.1.0',
    ]);
    curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $valueJson = [
        'url' => $url,
        'httpStatus' => $httpStatus,
        'responseTimeMs' => (int) round((microtime(true) - $started) * 1000),
    ];

    if ($error !== '') {
        $valueJson['error'] = $error;

        return ['status' => 'critical', 'valueJson' => $valueJson];
    }

    if ($httpStatus < $minStatus || $httpStatus > $maxStatus) {
        $valueJson['expectedStatusMin'] = $minStatus;
        $valueJson['expectedStatusMax'] = $maxStatus;

        return ['status' => 'critical', 'valueJson' => $valueJson];
    }

    return ['status' => 'ok', 'valueJson' => $valueJson];
}

$jobsResponse = request('GET', $apiUrl.'/api/v1/internal/probe-jobs', $token);
if ($jobsResponse['status'] !== 200) {
    fwrite(STDERR, 'Failed to fetch probe jobs: HTTP '.$jobsResponse['status'].PHP_EOL);
    exit(1);
}

$jobs = $jobsResponse['body']['items'] ?? [];
foreach ($jobs as $job) {
    if (!is_array($job) || ($job['type'] ?? '') !== 'uptime_http') {
        continue;
    }

    $result = runHttpProbe($job);
    $payload = json_encode([
        'checkId' => $job['checkId'],
        'probeId' => $probeId,
        'status' => $result['status'],
        'valueJson' => $result['valueJson'],
    ], JSON_THROW_ON_ERROR);

    $post = request('POST', $apiUrl.'/api/v1/internal/probe-results', $token, $payload);
    if ($post['status'] >= 300) {
        fwrite(STDERR, 'Failed to post probe result for '.$job['checkId'].PHP_EOL);
    }
}

echo sprintf("[%s] probe %s processed %d jobs\n", gmdate('c'), $probeId, count($jobs));

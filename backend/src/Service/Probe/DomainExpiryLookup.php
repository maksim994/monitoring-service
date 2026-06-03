<?php

declare(strict_types=1);

namespace App\Service\Probe;

/**
 * Best-effort domain expiry via IANA RDAP bootstrap (MVP).
 */
final class DomainExpiryLookup
{
    private const IANA_BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';

    /** @var list<array{0: list<string>, 1: list<string>}>|null */
    private ?array $bootstrapServices = null;

    public function lookup(string $domain): DomainExpiryLookupResult
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,63}$/', $domain)) {
            return DomainExpiryLookupResult::failed('Invalid domain name');
        }

        $baseUrl = $this->resolveRdapBaseUrl($domain);
        if ($baseUrl === null) {
            return DomainExpiryLookupResult::failed('RDAP server not found for TLD');
        }

        $url = rtrim($baseUrl, '/').'/domain/'.rawurlencode($domain);
        $payload = $this->fetchJson($url);
        if ($payload === null) {
            return DomainExpiryLookupResult::failed('RDAP request failed');
        }

        $expiresAt = $this->extractExpirationDate($payload);
        if ($expiresAt === null) {
            return DomainExpiryLookupResult::failed('Expiration date not found in RDAP response');
        }

        $daysLeft = (int) floor(($expiresAt->getTimestamp() - time()) / 86400);

        return new DomainExpiryLookupResult($expiresAt, $daysLeft, null, 'rdap');
    }

    private function resolveRdapBaseUrl(string $domain): ?string
    {
        $tld = $this->extractTld($domain);
        if ($tld === '') {
            return null;
        }

        foreach ($this->loadBootstrapServices() as $service) {
            $tlds = $service[0];
            $urls = $service[1];
            if (!in_array($tld, $tlds, true) || $urls === []) {
                continue;
            }

            return $urls[0];
        }

        return null;
    }

    private function extractTld(string $domain): string
    {
        $parts = explode('.', $domain);

        return count($parts) >= 2 ? (string) end($parts) : '';
    }

    /** @return list<array{0: list<string>, 1: list<string>}> */
    private function loadBootstrapServices(): array
    {
        if ($this->bootstrapServices !== null) {
            return $this->bootstrapServices;
        }

        $payload = $this->fetchJson(self::IANA_BOOTSTRAP_URL);
        if (!is_array($payload) || !isset($payload['services']) || !is_array($payload['services'])) {
            $this->bootstrapServices = [];

            return $this->bootstrapServices;
        }

        $services = [];
        foreach ($payload['services'] as $service) {
            if (!is_array($service) || count($service) < 2) {
                continue;
            }

            $tlds = is_array($service[0]) ? array_map('strval', $service[0]) : [];
            $urls = is_array($service[1]) ? array_map('strval', $service[1]) : [];
            $services[] = [$tlds, $urls];
        }

        $this->bootstrapServices = $services;

        return $this->bootstrapServices;
    }

    /** @param array<string, mixed> $payload */
    private function extractExpirationDate(array $payload): ?\DateTimeImmutable
    {
        $events = $payload['events'] ?? null;
        if (!is_array($events)) {
            return null;
        }

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $action = $event['eventAction'] ?? null;
            if ($action !== 'expiration') {
                continue;
            }

            $date = $event['eventDate'] ?? null;
            if (!is_string($date) || $date === '') {
                continue;
            }

            try {
                return new \DateTimeImmutable($date);
            } catch (\Exception) {
                continue;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function fetchJson(string $url): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json'],
            CURLOPT_USERAGENT => 'MonitoringProbe/0.1.0',
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400 || !is_string($response) || $response === '') {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}

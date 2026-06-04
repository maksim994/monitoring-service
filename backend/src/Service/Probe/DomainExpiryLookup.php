<?php

declare(strict_types=1);

namespace App\Service\Probe;

/**
 * Best-effort domain expiry via IANA RDAP bootstrap with TLD fallbacks and WHOIS for .ru.
 */
final class DomainExpiryLookup
{
    private const IANA_BOOTSTRAP_URL = 'https://data.iana.org/rdap/dns.json';

    /** @var array<string, string> */
    private const TLD_RDAP_FALLBACKS = [
        'ru' => 'https://rdap.nic.ru/',
        'su' => 'https://rdap.nic.ru/',
    ];

    /** @var array<string, string> */
    private const TLD_WHOIS_HOSTS = [
        'ru' => 'whois.tcinet.ru',
        'su' => 'whois.tcinet.ru',
    ];

    /** @var list<array{0: list<string>, 1: list<string>}>|null */
    private ?array $bootstrapServices = null;

    public function lookup(string $domain): DomainExpiryLookupResult
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,63}$/', $domain)) {
            return DomainExpiryLookupResult::failed('Invalid domain name');
        }

        $rdap = $this->lookupViaRdap($domain);
        if ($rdap->isSuccess()) {
            return $rdap;
        }

        $whois = $this->lookupViaWhois($domain);
        if ($whois->isSuccess()) {
            return $whois;
        }

        return DomainExpiryLookupResult::failed($whois->error ?? $rdap->error ?? 'Domain expiry lookup failed');
    }

    private function lookupViaRdap(string $domain): DomainExpiryLookupResult
    {
        $baseUrl = $this->resolveRdapBaseUrl($domain);
        if ($baseUrl === null) {
            return DomainExpiryLookupResult::failed('RDAP server not found for TLD');
        }

        $url = rtrim($baseUrl, '/').'/domain/'.rawurlencode($domain);
        $payload = $this->fetchJson($url);
        if ($payload === null) {
            return DomainExpiryLookupResult::failed('RDAP request failed');
        }

        if (isset($payload['errorCode'])) {
            $title = is_string($payload['title'] ?? null) ? $payload['title'] : 'RDAP error';

            return DomainExpiryLookupResult::failed($title);
        }

        $expiresAt = $this->extractExpirationDate($payload);
        if ($expiresAt === null) {
            return DomainExpiryLookupResult::failed('Expiration date not found in RDAP response');
        }

        $daysLeft = (int) floor(($expiresAt->getTimestamp() - time()) / 86400);

        return new DomainExpiryLookupResult($expiresAt, $daysLeft, null, 'rdap');
    }

    private function lookupViaWhois(string $domain): DomainExpiryLookupResult
    {
        $tld = $this->extractTld($domain);
        $host = self::TLD_WHOIS_HOSTS[$tld] ?? null;
        if ($host === null) {
            return DomainExpiryLookupResult::failed('WHOIS fallback not configured for TLD');
        }

        $response = $this->queryWhois($domain, $host);
        if ($response === null || $response === '') {
            return DomainExpiryLookupResult::failed('WHOIS request failed');
        }

        $expiresAt = $this->parseWhoisExpiration($response);
        if ($expiresAt === null) {
            return DomainExpiryLookupResult::failed('Expiration date not found in WHOIS response');
        }

        $daysLeft = (int) floor(($expiresAt->getTimestamp() - time()) / 86400);

        return new DomainExpiryLookupResult($expiresAt, $daysLeft, null, 'whois');
    }

    private function resolveRdapBaseUrl(string $domain): ?string
    {
        $tld = $this->extractTld($domain);
        if ($tld !== '' && isset(self::TLD_RDAP_FALLBACKS[$tld])) {
            return self::TLD_RDAP_FALLBACKS[$tld];
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

    private function queryWhois(string $domain, string $host): ?string
    {
        $socket = @fsockopen($host, 43, $errno, $errstr, 10);
        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, 10);
        fwrite($socket, $domain."\r\n");

        $response = '';
        while (!feof($socket)) {
            $chunk = fgets($socket, 8192);
            if ($chunk === false) {
                break;
            }

            $response .= $chunk;
        }

        fclose($socket);

        return $response !== '' ? $response : null;
    }

    private function parseWhoisExpiration(string $body): ?\DateTimeImmutable
    {
        $patterns = [
            '/^paid-till:\s*(\S+)/mi',
            '/^registry expiry date:\s*(\S+)/mi',
            '/^expiration date:\s*(\S+)/mi',
            '/^expiry date:\s*(\S+)/mi',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $body, $matches)) {
                continue;
            }

            try {
                return new \DateTimeImmutable($matches[1]);
            } catch (\Exception) {
                continue;
            }
        }

        return null;
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

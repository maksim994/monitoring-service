<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\HttpFoundation\Request;

final class ModuleSignatureVerifier
{
    public function __construct(
        private readonly int $signatureWindowSeconds,
    ) {
    }

    public function verify(Request $request, string $secret, ?string $rawBody = null): void
    {
        $siteId = $request->headers->get('X-Site-Id');
        $timestamp = $request->headers->get('X-Timestamp');
        $signature = $request->headers->get('X-Signature');
        $requestId = $request->headers->get('X-Request-Id');
        $moduleVersion = $request->headers->get('X-Module-Version');

        if (!$siteId || !$timestamp || !$signature || !$requestId || !$moduleVersion) {
            throw new ModuleAuthException('signature_missing_headers', 'Required module headers are missing.');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) {
            throw new ModuleAuthException('signature_invalid', 'Signature format is invalid.');
        }

        $requestTime = $this->parseTimestamp($timestamp);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $delta = abs($now->getTimestamp() - $requestTime->getTimestamp());

        if ($delta > $this->signatureWindowSeconds) {
            throw new ModuleAuthException('signature_timestamp_invalid', 'Request timestamp is outside the allowed window.');
        }

        $payload = $timestamp.'.'.($rawBody ?? $this->buildCanonicalPayload($request));
        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            throw new ModuleAuthException('signature_invalid', 'HMAC signature mismatch.');
        }
    }

    private function buildCanonicalPayload(Request $request): string
    {
        return strtoupper($request->getMethod()).'.'.$request->getPathInfo();
    }

    private function parseTimestamp(string $timestamp): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $timestamp)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $timestamp);

        if ($parsed === false) {
            throw new ModuleAuthException('signature_timestamp_invalid', 'Timestamp format is invalid.');
        }

        return $parsed->setTimezone(new \DateTimeZone('UTC'));
    }
}

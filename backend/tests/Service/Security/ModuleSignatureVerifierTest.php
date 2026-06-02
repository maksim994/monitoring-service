<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Service\Security\ModuleAuthException;
use App\Service\Security\ModuleSignatureVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ModuleSignatureVerifierTest extends TestCase
{
    private ModuleSignatureVerifier $verifier;

    protected function setUp(): void
    {
        $this->verifier = new ModuleSignatureVerifier(300);
    }

    public function testAcceptsValidSignatureForJsonBody(): void
    {
        $secret = 'test-secret';
        $body = '{"eventId":"abc"}';
        $timestamp = gmdate('c');
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $request = Request::create(
            '/api/v1/heartbeat',
            'POST',
            content: $body,
            server: [
                'HTTP_X_SITE_ID' => '019e8854-e8d3-7d92-8c17-3c39cd3761c2',
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
                'HTTP_X_REQUEST_ID' => '019e8854-e8d4-7625-9bad-7582562cd04c',
                'HTTP_X_MODULE_VERSION' => '0.1.0',
            ],
        );

        $this->verifier->verify($request, $secret, $body);
        $this->addToAssertionCount(1);
    }

    public function testRejectsInvalidSignature(): void
    {
        $request = Request::create(
            '/api/v1/heartbeat',
            'POST',
            content: '{}',
            server: [
                'HTTP_X_SITE_ID' => '019e8854-e8d3-7d92-8c17-3c39cd3761c2',
                'HTTP_X_TIMESTAMP' => gmdate('c'),
                'HTTP_X_SIGNATURE' => str_repeat('a', 64),
                'HTTP_X_REQUEST_ID' => '019e8854-e8d4-7625-9bad-7582562cd04d',
                'HTTP_X_MODULE_VERSION' => '0.1.0',
            ],
        );

        $this->expectException(ModuleAuthException::class);
        $this->expectExceptionMessage('HMAC signature mismatch');

        $this->verifier->verify($request, 'test-secret', '{}');
    }

    public function testRejectsExpiredTimestamp(): void
    {
        $secret = 'test-secret';
        $body = '{}';
        $timestamp = gmdate('c', time() - 600);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $request = Request::create(
            '/api/v1/heartbeat',
            'POST',
            content: $body,
            server: [
                'HTTP_X_SITE_ID' => '019e8854-e8d3-7d92-8c17-3c39cd3761c2',
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
                'HTTP_X_REQUEST_ID' => '019e8854-e8d4-7625-9bad-7582562cd04e',
                'HTTP_X_MODULE_VERSION' => '0.1.0',
            ],
        );

        $this->expectException(ModuleAuthException::class);
        $this->expectExceptionMessage('outside the allowed window');

        $this->verifier->verify($request, $secret, $body);
    }
}

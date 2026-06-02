<?php

declare(strict_types=1);

namespace App\Tests\Service\Notification;

use App\Service\Notification\WebhookUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookUrlValidatorTest extends TestCase
{
    private WebhookUrlValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new WebhookUrlValidator();
    }

    #[DataProvider('allowedUrlsProvider')]
    public function testAllowsPublicWebhookUrls(string $url): void
    {
        $this->validator->assertSafe($url);
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function allowedUrlsProvider(): iterable
    {
        yield 'https public host' => ['https://example.com/webhook'];
        yield 'http public host' => ['http://example.org/webhook'];
    }

    #[DataProvider('blockedUrlsProvider')]
    public function testBlocksUnsafeWebhookUrls(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->assertSafe($url);
    }

    /** @return iterable<string, array{string}> */
    public static function blockedUrlsProvider(): iterable
    {
        yield 'localhost' => ['http://localhost/hook'];
        yield 'private ip' => ['http://192.168.1.10/hook'];
        yield 'loopback ip' => ['http://127.0.0.1/hook'];
        yield 'metadata ip' => ['http://169.254.169.254/latest/meta-data'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'local domain' => ['https://service.local/hook'];
    }
}

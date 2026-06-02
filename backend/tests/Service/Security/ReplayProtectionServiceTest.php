<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Service\Security\ModuleAuthException;
use App\Service\Security\ReplayProtectionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ReplayProtectionServiceTest extends TestCase
{
    public function testDetectsReplayedRequestId(): void
    {
        $service = new ReplayProtectionService(new ArrayAdapter());

        $service->assertNotReplayed('site-1', 'request-1');

        $this->expectException(ModuleAuthException::class);
        $this->expectExceptionMessage('already been used');

        $service->assertNotReplayed('site-1', 'request-1');
    }

    public function testAllowsDifferentRequestIds(): void
    {
        $service = new ReplayProtectionService(new ArrayAdapter());

        $service->assertNotReplayed('site-1', 'request-1');
        $service->assertNotReplayed('site-1', 'request-2');

        $this->addToAssertionCount(1);
    }
}

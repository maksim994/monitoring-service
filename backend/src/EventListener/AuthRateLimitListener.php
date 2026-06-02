<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 256)]
final class AuthRateLimitListener
{
    private const AUTH_PATHS = [
        '/api/v1/auth/login' => true,
        '/api/v1/auth/register' => true,
    ];

    public function __construct(
        private readonly RateLimiterFactory $authLimiter,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->getMethod() !== 'POST' || !isset(self::AUTH_PATHS[$request->getPathInfo()])) {
            return;
        }

        $key = sprintf('%s:%s', $request->getPathInfo(), $request->getClientIp() ?? 'unknown');
        $limiter = $this->authLimiter->create($key);
        $limit = $limiter->consume(1);

        if ($limit->isAccepted()) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Too many authentication attempts. Please try again later.',
            ],
            'requestId' => bin2hex(random_bytes(8)),
        ], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string) max(1, $limit->getRetryAfter()?->getTimestamp() - time()),
        ]));
    }
}

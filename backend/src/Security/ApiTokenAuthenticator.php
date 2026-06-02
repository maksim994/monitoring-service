<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if (!str_starts_with($request->getPathInfo(), '/api/v1/')) {
            return false;
        }

        if (in_array($request->getPathInfo(), [
            '/api/v1/auth/register',
            '/api/v1/auth/login',
        ], true)) {
            return false;
        }

        if (in_array($request->getPathInfo(), [
            '/api/v1/sites/handshake',
            '/api/v1/heartbeat',
            '/api/v1/metrics/batch',
            '/api/v1/events/batch',
            '/api/v1/module/config',
        ], true)) {
            return false;
        }

        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorization = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/', $authorization, $matches)) {
            throw new CustomUserMessageAuthenticationException('Invalid authorization header.');
        }

        $token = $matches[1];
        $user = $this->userRepository->findOneByApiToken($token);
        if (!$user instanceof User) {
            throw new CustomUserMessageAuthenticationException('Invalid API token.');
        }

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => [
                'code' => 'unauthorized',
                'message' => $exception->getMessageKey(),
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }
}

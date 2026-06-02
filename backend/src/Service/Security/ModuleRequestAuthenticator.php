<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Site;
use App\Repository\SiteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class ModuleRequestAuthenticator
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly SiteKeyService $siteKeyService,
        private readonly ModuleSignatureVerifier $signatureVerifier,
        private readonly ReplayProtectionService $replayProtectionService,
    ) {
    }

    public function authenticate(Request $request, ?string $rawBody = null): Site
    {
        $siteId = $request->headers->get('X-Site-Id');
        if (!$siteId) {
            throw new ModuleAuthException('signature_missing_headers', 'X-Site-Id header is required.');
        }

        if (!Uuid::isValid($siteId)) {
            throw new ModuleAuthException('signature_site_not_found', 'Site id is invalid.');
        }

        $site = $this->siteRepository->find(Uuid::fromString($siteId));
        if ($site === null) {
            throw new ModuleAuthException('signature_site_not_found', 'Site was not found.');
        }

        if ($site->getStatus() === Site::STATUS_DISABLED) {
            throw new ModuleAuthException('signature_site_disabled', 'Site is disabled.');
        }

        $secret = $this->siteKeyService->getActiveSecret($site);
        if ($secret === null) {
            throw new ModuleAuthException('signature_key_revoked', 'Active site key was not found.');
        }

        $this->signatureVerifier->verify($request, $secret, $rawBody);
        $this->replayProtectionService->assertNotReplayed($siteId, (string) $request->headers->get('X-Request-Id'));

        return $site;
    }
}

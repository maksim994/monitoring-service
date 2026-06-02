<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Site;
use App\Entity\SiteKey;
use App\Repository\SiteKeyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SiteKeyService
{
    public function __construct(
        private readonly SiteKeyRepository $siteKeyRepository,
        private readonly SecretEncrypter $secretEncrypter,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createKey(Site $site): array
    {
        $secret = bin2hex(random_bytes(32));
        $siteKey = new SiteKey($site, $this->secretEncrypter->encrypt($secret));
        $this->entityManager->persist($siteKey);

        return [
            'siteKey' => $siteKey,
            'secret' => $secret,
        ];
    }

    public function rotateKey(Site $site): array
    {
        $existingKey = $this->siteKeyRepository->findActiveKeyForSite($site);
        if ($existingKey !== null) {
            $existingKey->revoke();
        }

        return $this->createKey($site);
    }

    public function getActiveSecret(Site $site): ?string
    {
        $siteKey = $this->siteKeyRepository->findActiveKeyForSite($site);
        if ($siteKey === null || !$siteKey->isActive()) {
            return null;
        }

        return $this->secretEncrypter->decrypt($siteKey->getSecretEncrypted());
    }
}

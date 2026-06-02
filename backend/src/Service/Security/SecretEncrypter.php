<?php

declare(strict_types=1);

namespace App\Service\Security;

final class SecretEncrypter
{
    public function __construct(
        private readonly string $appSecret,
    ) {
    }

    public function encrypt(string $plainText): string
    {
        $key = $this->deriveKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plainText, $nonce, $key);

        return base64_encode($nonce.$cipher);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid encrypted secret payload.');
        }

        $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($decoded) <= $nonceLength) {
            throw new \InvalidArgumentException('Encrypted secret payload is too short.');
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $cipher = substr($decoded, $nonceLength);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->deriveKey());

        if ($plain === false) {
            throw new \InvalidArgumentException('Unable to decrypt secret.');
        }

        return $plain;
    }

    private function deriveKey(): string
    {
        return sodium_crypto_generichash($this->appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}

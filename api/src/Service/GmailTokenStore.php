<?php

namespace App\Service;

final class GmailTokenStore
{
    private string $tokenFile;
    private string $stateFile;

    public function __construct(private string $privateDir, private string $encryptionKey = '')
    {
        if (!is_dir($privateDir)) @mkdir($privateDir, 0700, true);
        $this->tokenFile = $privateDir.'/gmail-token.enc';
        $this->stateFile = $privateDir.'/gmail-oauth-state';
    }

    public function saveToken(array $token): void
    {
        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox(json_encode($token, JSON_THROW_ON_ERROR), $nonce, $key);
        file_put_contents($this->tokenFile, base64_encode($nonce.$cipher), LOCK_EX);
        chmod($this->tokenFile, 0600);
    }

    public function getToken(): ?array
    {
        if (!is_file($this->tokenFile)) return null;
        $raw = base64_decode((string) file_get_contents($this->tokenFile), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());
        return $plain === false ? null : json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    public function clear(): void { if (is_file($this->tokenFile)) unlink($this->tokenFile); }
    public function isConnected(): bool { return $this->getToken() !== null; }

    public function createState(): string
    {
        $state = bin2hex(random_bytes(24));
        file_put_contents($this->stateFile, $state, LOCK_EX);
        chmod($this->stateFile, 0600);
        return $state;
    }

    public function consumeState(string $state): bool
    {
        if (!is_file($this->stateFile)) return false;
        $expected = trim((string) file_get_contents($this->stateFile));
        unlink($this->stateFile);
        return hash_equals($expected, $state);
    }

    private function key(): string
    {
        $decoded = base64_decode($this->encryptionKey, true);
        if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) return $decoded;
        return sodium_crypto_generichash($this->encryptionKey !== '' ? $this->encryptionKey : 'jobpilot-local-development-key', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}

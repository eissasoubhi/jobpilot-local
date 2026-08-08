<?php

declare(strict_types=1);

namespace App\Service\Ai;

final class AiMatchingConfigurationStore
{
    private string $file;

    public function __construct(
        string $privateDir,
        private readonly string $encryptionKey,
        private readonly bool $environmentEnabled,
        private readonly string $environmentApiKey,
        private readonly string $environmentModel,
        private readonly int $environmentQuotaRpm = 15,
        private readonly int $environmentQuotaTpm = 250000,
        private readonly int $environmentQuotaRpd = 500,
        private readonly int $environmentQuotaSafetyPercent = 80,
    ) {
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0700, true);
        }

        $this->file = rtrim($privateDir, '/').'/ai-matching-config.enc';
    }

    /** @return array{provider: string, enabled: bool, model: string, apiKey: string, quota: array{rpm: int, tpm: int, rpd: int, safetyPercent: int}} */
    public function effective(): array
    {
        $overrides = $this->readOverrides();

        return [
            'provider' => 'gemini',
            'enabled' => array_key_exists('enabled', $overrides)
                ? (bool) $overrides['enabled']
                : $this->environmentEnabled,
            'model' => isset($overrides['model']) && is_string($overrides['model']) && trim($overrides['model']) !== ''
                ? trim($overrides['model'])
                : $this->environmentModel,
            'apiKey' => isset($overrides['apiKey']) && is_string($overrides['apiKey']) && trim($overrides['apiKey']) !== ''
                ? trim($overrides['apiKey'])
                : trim($this->environmentApiKey),
            'quota' => [
                'rpm' => $this->positiveIntegerOverride($overrides, 'quotaRpm', $this->environmentQuotaRpm),
                'tpm' => $this->positiveIntegerOverride($overrides, 'quotaTpm', $this->environmentQuotaTpm),
                'rpd' => $this->positiveIntegerOverride($overrides, 'quotaRpd', $this->environmentQuotaRpd),
                'safetyPercent' => $this->boundedIntegerOverride(
                    $overrides,
                    'quotaSafetyPercent',
                    $this->environmentQuotaSafetyPercent,
                    1,
                    100,
                ),
            ],
        ];
    }

    /** @return array{provider: string, enabled: bool, model: string, apiKeyConfigured: bool, apiKeySource: string, hasInterfaceOverrides: bool, quota: array{rpm: int, tpm: int, rpd: int, safetyPercent: int}} */
    public function publicConfiguration(): array
    {
        $overrides = $this->readOverrides();
        $effective = $this->effective();
        $interfaceKey = isset($overrides['apiKey']) && is_string($overrides['apiKey']) && trim($overrides['apiKey']) !== '';
        $environmentKey = trim($this->environmentApiKey) !== '';

        return [
            'provider' => $effective['provider'],
            'enabled' => $effective['enabled'],
            'model' => $effective['model'],
            'apiKeyConfigured' => $effective['apiKey'] !== '',
            'apiKeySource' => $interfaceKey ? 'interface' : ($environmentKey ? 'environment' : 'none'),
            'hasInterfaceOverrides' => $overrides !== [],
            'quota' => $effective['quota'],
        ];
    }

    /**
     * Empty apiKey values preserve the currently stored key. Set clearApiKey=true
     * to remove only the UI-stored secret and fall back to the environment.
     *
     * @param array<string, mixed> $input
     * @return array{provider: string, enabled: bool, model: string, apiKeyConfigured: bool, apiKeySource: string, hasInterfaceOverrides: bool, quota: array{rpm: int, tpm: int, rpd: int, safetyPercent: int}}
     */
    public function save(array $input): array
    {
        $overrides = $this->readOverrides();

        if (array_key_exists('enabled', $input)) {
            $overrides['enabled'] = (bool) $input['enabled'];
        }

        if (array_key_exists('model', $input)) {
            $model = trim((string) $input['model']);
            if ($model === '' || mb_strlen($model) > 120) {
                throw new \InvalidArgumentException('Le modèle IA doit contenir entre 1 et 120 caractères.');
            }
            $overrides['model'] = $model;
        }

        foreach ([
            'quotaRpm' => [1, 100000],
            'quotaTpm' => [1, 1000000000],
            'quotaRpd' => [1, 10000000],
            'quotaSafetyPercent' => [1, 100],
        ] as $field => [$minimum, $maximum]) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            if (!is_numeric($input[$field])) {
                throw new \InvalidArgumentException(sprintf('La valeur %s doit être numérique.', $field));
            }

            $value = (int) $input[$field];
            if ($value < $minimum || $value > $maximum) {
                throw new \InvalidArgumentException(sprintf('La valeur %s doit être comprise entre %d et %d.', $field, $minimum, $maximum));
            }

            $overrides[$field] = $value;
        }

        if (($input['clearApiKey'] ?? false) === true) {
            unset($overrides['apiKey']);
        } elseif (array_key_exists('apiKey', $input)) {
            $apiKey = trim((string) $input['apiKey']);
            if ($apiKey !== '') {
                if (mb_strlen($apiKey) > 500) {
                    throw new \InvalidArgumentException('La clé API est trop longue.');
                }
                $overrides['apiKey'] = $apiKey;
            }
        }

        $this->writeOverrides($overrides);

        return $this->publicConfiguration();
    }

    /** @param array<string, mixed> $overrides */
    private function positiveIntegerOverride(array $overrides, string $key, int $fallback): int
    {
        return $this->boundedIntegerOverride($overrides, $key, max(1, $fallback), 1, PHP_INT_MAX);
    }

    /** @param array<string, mixed> $overrides */
    private function boundedIntegerOverride(array $overrides, string $key, int $fallback, int $minimum, int $maximum): int
    {
        if (!isset($overrides[$key]) || !is_numeric($overrides[$key])) {
            return max($minimum, min($maximum, $fallback));
        }

        return max($minimum, min($maximum, (int) $overrides[$key]));
    }

    /** @return array<string, mixed> */
    private function readOverrides(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $encoded = file_get_contents($this->file);
        if ($encoded === false || $encoded === '') {
            return [];
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return [];
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());
        if ($plain === false) {
            return [];
        }

        try {
            $decoded = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $overrides */
    private function writeOverrides(array $overrides): void
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = json_encode($overrides, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key());

        if (file_put_contents($this->file, base64_encode($nonce.$cipher), LOCK_EX) === false) {
            throw new \RuntimeException('Impossible d’enregistrer la configuration IA chiffrée.');
        }

        chmod($this->file, 0600);
    }

    private function key(): string
    {
        $decoded = base64_decode($this->encryptionKey, true);
        if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $decoded;
        }

        return sodium_crypto_generichash(
            $this->encryptionKey !== '' ? $this->encryptionKey : 'jobpilot-local-development-key',
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Service\Integration;

final class ExternalIntegrationConfigurationStore
{
    private const DEFINITIONS = [
        'openai' => [
            'label' => 'OpenAI',
            'category' => 'ai',
            'runtimeActive' => false,
            'note' => 'Clé conservée pour une future bascule du provider de matching.',
            'fields' => [
                'model' => ['label' => 'Modèle', 'secret' => false, 'maxLength' => 120],
                'apiKey' => ['label' => 'Clé API', 'secret' => true, 'maxLength' => 500],
            ],
        ],
        'mistral' => [
            'label' => 'Mistral',
            'category' => 'ai',
            'runtimeActive' => false,
            'note' => 'Clé conservée pour une future bascule du provider de matching.',
            'fields' => [
                'model' => ['label' => 'Modèle', 'secret' => false, 'maxLength' => 120],
                'apiKey' => ['label' => 'Clé API', 'secret' => true, 'maxLength' => 500],
            ],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'category' => 'ai',
            'runtimeActive' => false,
            'note' => 'Clé conservée pour une future bascule du provider de matching.',
            'fields' => [
                'model' => ['label' => 'Modèle', 'secret' => false, 'maxLength' => 120],
                'apiKey' => ['label' => 'Clé API', 'secret' => true, 'maxLength' => 500],
            ],
        ],
        'adzuna' => [
            'label' => 'Adzuna',
            'category' => 'connector',
            'runtimeActive' => true,
            'note' => 'Ces identifiants sont utilisés immédiatement par le connecteur Adzuna.',
            'fields' => [
                'appId' => ['label' => 'App ID', 'secret' => false, 'maxLength' => 200],
                'appKey' => ['label' => 'App key', 'secret' => true, 'maxLength' => 500],
            ],
        ],
        'france-travail' => [
            'label' => 'France Travail',
            'category' => 'connector',
            'runtimeActive' => true,
            'note' => 'Ces identifiants sont utilisés immédiatement par le connecteur France Travail.',
            'fields' => [
                'clientId' => ['label' => 'Client ID', 'secret' => false, 'maxLength' => 300],
                'clientSecret' => ['label' => 'Client secret', 'secret' => true, 'maxLength' => 500],
            ],
        ],
        'smartrecruiters' => [
            'label' => 'SmartRecruiters',
            'category' => 'connector',
            'runtimeActive' => true,
            'note' => 'Le token et les identifiants d’entreprise sont utilisés immédiatement par le connecteur.',
            'fields' => [
                'companyIdentifiers' => ['label' => 'Identifiants entreprises', 'secret' => false, 'maxLength' => 1000],
                'apiToken' => ['label' => 'API token', 'secret' => true, 'maxLength' => 500],
            ],
        ],
    ];

    private string $file;

    /** @param array<string, array<string, string>> $environment */
    public function __construct(
        string $privateDir,
        private readonly string $encryptionKey,
        private readonly array $environment = [],
    ) {
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0700, true);
        }

        $this->file = rtrim($privateDir, '/').'/external-integrations.enc';
    }

    /** @return list<array<string, mixed>> */
    public function publicConfigurations(): array
    {
        $configurations = [];
        foreach (array_keys(self::DEFINITIONS) as $integration) {
            $configurations[] = $this->publicConfiguration($integration);
        }

        return $configurations;
    }

    /** @return array<string, string> */
    public function effective(string $integration): array
    {
        $definition = $this->definition($integration);
        $overrides = $this->readOverrides();
        $stored = is_array($overrides[$integration] ?? null) ? $overrides[$integration] : [];
        $environment = is_array($this->environment[$integration] ?? null) ? $this->environment[$integration] : [];
        $effective = [];

        foreach ($definition['fields'] as $field => $_metadata) {
            $interfaceValue = isset($stored[$field]) && is_string($stored[$field]) ? trim($stored[$field]) : '';
            $environmentValue = isset($environment[$field]) && is_string($environment[$field]) ? trim($environment[$field]) : '';
            $effective[$field] = $interfaceValue !== '' ? $interfaceValue : $environmentValue;
        }

        return $effective;
    }

    /** @return array<string, mixed> */
    public function publicConfiguration(string $integration): array
    {
        $definition = $this->definition($integration);
        $overrides = $this->readOverrides();
        $stored = is_array($overrides[$integration] ?? null) ? $overrides[$integration] : [];
        $environment = is_array($this->environment[$integration] ?? null) ? $this->environment[$integration] : [];
        $effective = $this->effective($integration);
        $fields = [];

        foreach ($definition['fields'] as $field => $metadata) {
            $interfaceValue = isset($stored[$field]) && is_string($stored[$field]) ? trim($stored[$field]) : '';
            $environmentValue = isset($environment[$field]) && is_string($environment[$field]) ? trim($environment[$field]) : '';
            $source = $interfaceValue !== '' ? 'interface' : ($environmentValue !== '' ? 'environment' : 'none');
            $secret = (bool) $metadata['secret'];

            $fields[$field] = [
                'label' => $metadata['label'],
                'secret' => $secret,
                'configured' => ($effective[$field] ?? '') !== '',
                'source' => $source,
                'value' => $secret ? null : ($effective[$field] ?? ''),
            ];
        }

        return [
            'id' => $integration,
            'label' => $definition['label'],
            'category' => $definition['category'],
            'runtimeActive' => $definition['runtimeActive'],
            'note' => $definition['note'],
            'fields' => $fields,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(string $integration, array $input): array
    {
        $definition = $this->definition($integration);
        $overrides = $this->readOverrides();
        $stored = is_array($overrides[$integration] ?? null) ? $overrides[$integration] : [];
        $values = is_array($input['values'] ?? null) ? $input['values'] : [];
        $secrets = is_array($input['secrets'] ?? null) ? $input['secrets'] : [];
        $clearSecrets = is_array($input['clearSecrets'] ?? null) ? $input['clearSecrets'] : [];

        foreach ($definition['fields'] as $field => $metadata) {
            $secret = (bool) $metadata['secret'];
            if ($secret) {
                if (in_array($field, $clearSecrets, true)) {
                    unset($stored[$field]);
                    continue;
                }

                if (!array_key_exists($field, $secrets)) {
                    continue;
                }

                $value = trim((string) $secrets[$field]);
                if ($value === '') {
                    continue;
                }
            } else {
                if (!array_key_exists($field, $values)) {
                    continue;
                }

                $value = trim((string) $values[$field]);
                if ($value === '') {
                    unset($stored[$field]);
                    continue;
                }
            }

            if (mb_strlen($value) > (int) $metadata['maxLength']) {
                throw new \InvalidArgumentException(sprintf('La valeur « %s » est trop longue.', $metadata['label']));
            }

            $stored[$field] = $value;
        }

        if ($stored === []) {
            unset($overrides[$integration]);
        } else {
            $overrides[$integration] = $stored;
        }

        $this->writeOverrides($overrides);

        return $this->publicConfiguration($integration);
    }

    /** @return array<string, mixed> */
    private function definition(string $integration): array
    {
        if (!isset(self::DEFINITIONS[$integration])) {
            throw new \InvalidArgumentException('Intégration inconnue.');
        }

        return self::DEFINITIONS[$integration];
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
            throw new \RuntimeException('Impossible d’enregistrer la configuration chiffrée des intégrations.');
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

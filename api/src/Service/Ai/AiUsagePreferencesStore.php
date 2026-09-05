<?php

declare(strict_types=1);

namespace App\Service\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AiUsagePreferencesStore
{
    private string $file;

    public function __construct(#[Autowire('%private_dir%')] string $privateDir)
    {
        if (!is_dir($privateDir)) {
            @mkdir($privateDir, 0700, true);
        }

        $this->file = rtrim($privateDir, '/').'/ai-usage-preferences.json';
    }

    /** @return array{billingTier: string, usdToEurRate: ?float, prepaidCreditUsd: ?float, prepaidCreditSetAt: ?int} */
    public function get(): array
    {
        $stored = $this->read();

        return [
            'billingTier' => ($stored['billingTier'] ?? null) === 'free' ? 'free' : 'paid',
            'usdToEurRate' => is_numeric($stored['usdToEurRate'] ?? null) ? (float) $stored['usdToEurRate'] : null,
            'prepaidCreditUsd' => is_numeric($stored['prepaidCreditUsd'] ?? null) ? max(0.0, (float) $stored['prepaidCreditUsd']) : null,
            'prepaidCreditSetAt' => is_numeric($stored['prepaidCreditSetAt'] ?? null) ? (int) $stored['prepaidCreditSetAt'] : null,
        ];
    }

    /** @param array<string, mixed> $input @return array{billingTier: string, usdToEurRate: ?float, prepaidCreditUsd: ?float, prepaidCreditSetAt: ?int} */
    public function save(array $input): array
    {
        $stored = $this->read();

        if (array_key_exists('billingTier', $input)) {
            $tier = strtolower(trim((string) $input['billingTier']));
            if (!in_array($tier, ['paid', 'free'], true)) {
                throw new \InvalidArgumentException('Le niveau de facturation IA doit être paid ou free.');
            }
            $stored['billingTier'] = $tier;
        }

        if (($input['clearUsdToEurRate'] ?? false) === true) {
            unset($stored['usdToEurRate']);
        } elseif (array_key_exists('usdToEurRate', $input)) {
            if (!is_numeric($input['usdToEurRate'])) {
                throw new \InvalidArgumentException('Le taux USD vers EUR doit être numérique.');
            }
            $rate = (float) $input['usdToEurRate'];
            if ($rate < 0.1 || $rate > 5.0) {
                throw new \InvalidArgumentException('Le taux USD vers EUR doit être compris entre 0,1 et 5.');
            }
            $stored['usdToEurRate'] = $rate;
        }

        if (($input['clearPrepaidCredit'] ?? false) === true) {
            unset($stored['prepaidCreditUsd'], $stored['prepaidCreditSetAt']);
        } elseif (array_key_exists('prepaidCreditUsd', $input)) {
            if (!is_numeric($input['prepaidCreditUsd'])) {
                throw new \InvalidArgumentException('Le crédit prépayé de référence doit être numérique.');
            }
            $credit = (float) $input['prepaidCreditUsd'];
            if ($credit < 0 || $credit > 1_000_000) {
                throw new \InvalidArgumentException('Le crédit prépayé de référence doit être compris entre 0 et 1 000 000 USD.');
            }

            $currentCredit = is_numeric($stored['prepaidCreditUsd'] ?? null)
                ? (float) $stored['prepaidCreditUsd']
                : null;
            $stored['prepaidCreditUsd'] = $credit;
            if ($currentCredit === null || abs($currentCredit - $credit) > 0.0000001 || !is_numeric($stored['prepaidCreditSetAt'] ?? null)) {
                $stored['prepaidCreditSetAt'] = time();
            }
        }

        $this->write($stored);

        return $this->get();
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $raw = file_get_contents($this->file);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $stored */
    private function write(array $stored): void
    {
        if (file_put_contents(
            $this->file,
            json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX,
        ) === false) {
            throw new \RuntimeException('Impossible d’enregistrer les préférences de suivi IA.');
        }
        chmod($this->file, 0600);
    }
}

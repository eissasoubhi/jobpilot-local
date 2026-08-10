<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

final class ConnectorSmokeResultEvaluator
{
    /**
     * @param array<string, mixed> $result
     * @return array{success: bool, message: string, metrics: array<string, int|bool|string|null>}
     */
    public function evaluate(array $result, string $connectorCode, bool $allowZero = false): array
    {
        $connectorCode = strtolower(trim($connectorCode));
        $metrics = [
            'connectorCode' => $connectorCode,
            'busy' => (bool) ($result['busy'] ?? false),
            'skipped' => (bool) ($result['skipped'] ?? false),
            'received' => 0,
            'imported' => 0,
            'merged' => 0,
            'duplicates' => 0,
            'profileFiltered' => 0,
            'failed' => 0,
        ];

        if ($metrics['busy']) {
            return $this->failure('Le smoke test n’a pas démarré car une synchronisation est déjà en cours.', $metrics);
        }
        if ($metrics['skipped']) {
            $reason = trim((string) ($result['message'] ?? ''));

            return $this->failure(
                $reason !== '' ? 'Smoke test ignoré : '.$reason : 'Le connecteur n’est pas exécutable dans son état actuel.',
                $metrics,
            );
        }

        $connectorResult = $this->connectorResult($result, $connectorCode);
        if ($connectorResult === null) {
            return $this->failure('Le résultat de synchronisation du connecteur demandé est absent.', $metrics);
        }

        foreach (['received', 'imported', 'merged', 'duplicates', 'profileFiltered', 'failed'] as $key) {
            $metrics[$key] = max(0, (int) ($connectorResult[$key] ?? 0));
        }

        if ($metrics['failed'] > 0) {
            $error = trim((string) ($connectorResult['error'] ?? ''));

            return $this->failure(
                sprintf(
                    'Smoke test en échec : %d erreur(s)%s.',
                    $metrics['failed'],
                    $error !== '' ? ' — '.$error : '',
                ),
                $metrics,
            );
        }

        if ($metrics['received'] === 0 && !$allowZero) {
            return $this->failure(
                'Le connecteur n’a produit aucune offre. Utiliser --allow-zero uniquement si zéro résultat est réellement attendu.',
                $metrics,
            );
        }

        $normalized = $metrics['imported'] + $metrics['merged'] + $metrics['duplicates'] + $metrics['profileFiltered'];
        if ($metrics['received'] > 0 && $normalized === 0) {
            return $this->failure(
                'Des offres ont été reçues mais aucune n’a atteint une issue canonique connue.',
                $metrics,
            );
        }

        $message = $metrics['received'] === 0
            ? 'Smoke test réussi avec zéro résultat explicitement autorisé.'
            : sprintf(
                'Smoke test réussi : %d reçue(s), %d nouvelle(s), %d fusionnée(s), %d connue(s), %d hors profil.',
                $metrics['received'],
                $metrics['imported'],
                $metrics['merged'],
                $metrics['duplicates'],
                $metrics['profileFiltered'],
            );

        return [
            'success' => true,
            'message' => $message,
            'metrics' => $metrics,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    private function connectorResult(array $result, string $connectorCode): ?array
    {
        $items = is_array($result['connectorResults'] ?? null)
            ? $result['connectorResults']
            : (is_array($result['providers'] ?? null) ? $result['providers'] : []);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (strtolower(trim((string) ($item['code'] ?? ''))) === $connectorCode) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, int|bool|string|null> $metrics
     * @return array{success: bool, message: string, metrics: array<string, int|bool|string|null>}
     */
    private function failure(string $message, array $metrics): array
    {
        return [
            'success' => false,
            'message' => $message,
            'metrics' => $metrics,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Ai\AiMatchingConfigurationStore;
use App\Service\Ai\AiQuotaManager;
use App\Service\Ai\AiUsageLedger;
use App\Service\Ai\AiUsagePreferencesStore;
use App\Service\Ai\AiUsagePricing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/ai/usage')]
final class AiUsageController
{
    public function __construct(
        private readonly AiMatchingConfigurationStore $configuration,
        private readonly AiQuotaManager $quotaManager,
        private readonly AiUsageLedger $ledger,
        private readonly AiUsagePreferencesStore $preferences,
        private readonly AiUsagePricing $pricing,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function get(Request $request): JsonResponse
    {
        $selectedDate = trim((string) $request->query->get('date', ''));
        if ($selectedDate === '') {
            $selectedDate = null;
        } elseif (!$this->validDate($selectedDate)) {
            return new JsonResponse(['error' => 'La date doit utiliser le format YYYY-MM-DD.'], 422);
        }

        return new JsonResponse($this->payload($selectedDate));
    }

    #[Route('/preferences', methods: ['PUT'])]
    public function savePreferences(Request $request): JsonResponse
    {
        try {
            $this->preferences->save($request->toArray());

            return new JsonResponse($this->payload(null));
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    /** @return array<string, mixed> */
    private function payload(?string $selectedDate): array
    {
        $public = $this->configuration->publicConfiguration();
        $effective = $this->configuration->effective();
        $preferences = $this->preferences->get();
        $quotaUsage = $this->quotaManager->status(
            'gemini',
            $effective['model'],
            $effective['quota'],
        );

        return [
            'provider' => $public['provider'],
            'enabled' => $public['enabled'],
            'model' => $public['model'],
            'apiKeyConfigured' => $public['apiKeyConfigured'],
            'quota' => $public['quota'],
            'quotaUsage' => $quotaUsage,
            'billing' => [
                ...$preferences,
                'prepaidCreditSetAtIso' => $preferences['prepaidCreditSetAt'] !== null
                    ? date(DATE_ATOM, $preferences['prepaidCreditSetAt'])
                    : null,
            ],
            'pricing' => $this->pricing->describe(
                'gemini',
                $effective['model'],
                $preferences['billingTier'],
            ),
            'usage' => $this->ledger->dashboard(
                $selectedDate,
                $preferences['usdToEurRate'],
                $preferences['prepaidCreditUsd'],
                $preferences['prepaidCreditSetAt'],
            ),
        ];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Europe/Paris'));

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value;
    }
}

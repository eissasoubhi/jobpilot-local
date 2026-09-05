<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\JobOffer;
use App\Service\JobProcessor;
use App\Service\LocalDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/profile')]
final class ProfileController
{
    public function __construct(
        private LocalDataService $data,
        private EntityManagerInterface $em,
        private JobProcessor $jobProcessor,
    ) {}

    #[Route('', methods: ['GET'])]
    public function get(): JsonResponse
    {
        return new JsonResponse($this->data->profile()->toArray());
    }

    #[Route('/autofill', methods: ['GET'])]
    public function autofill(): JsonResponse
    {
        return new JsonResponse($this->data->profile()->toAutofillArray());
    }

    #[Route('', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        $profile = $this->data->profile();
        $before = $this->searchPreferences($profile->toArray());
        $profile->fill($request->toArray());
        $after = $this->searchPreferences($profile->toArray());
        $this->em->flush();

        if ($before !== $after) {
            $settings = $this->data->settings();
            foreach ($this->em->getRepository(JobOffer::class)->findAll() as $job) {
                if ($job instanceof JobOffer) {
                    $this->jobProcessor->refreshSearchPreferences($job, $settings, $profile);
                }
            }
        }

        return new JsonResponse($profile->toArray());
    }

    /** @param array<string, mixed> $profile */
    private function searchPreferences(array $profile): array
    {
        $contracts = is_array($profile['acceptedContracts'] ?? null)
            ? array_values(array_unique(array_map(
                static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
                $profile['acceptedContracts'],
            )))
            : [];
        sort($contracts);

        return [
            'acceptedContracts' => $contracts,
            'workModePreference' => mb_strtolower(trim((string) ($profile['workModePreference'] ?? ''))),
        ];
    }
}

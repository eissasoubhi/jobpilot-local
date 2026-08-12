<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ApplicationQuestionSuggestionService;
use App\Service\ExtensionApplicationDocumentResolver;
use App\Service\LocalDataService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ExtensionQuestionController
{
    public function __construct(
        private readonly ExtensionApplicationDocumentResolver $applications,
        private readonly LocalDataService $data,
        private readonly ApplicationQuestionSuggestionService $suggestions,
    ) {
    }

    #[Route('/api/extension/question-suggestion', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Le JSON envoyé est invalide.'], 400);
        }

        $question = trim((string) ($payload['question'] ?? ''));
        if ($question === '' || mb_strlen($question) > 2000) {
            return new JsonResponse(['error' => 'La question est obligatoire et limitée à 2000 caractères.'], 400);
        }

        $language = strtolower(trim((string) ($payload['language'] ?? 'fr'))) === 'en' ? 'en' : 'fr';
        $maxLength = max(80, min(1500, (int) ($payload['maxLength'] ?? 600)));

        $resolved = $this->applications->resolve([
            'applicationId' => $payload['applicationId'] ?? null,
            'jobOfferId' => $payload['jobOfferId'] ?? null,
            'url' => $payload['url'] ?? null,
        ]);
        if ($resolved === null) {
            return new JsonResponse([
                'error' => 'Aucune candidature unique ne correspond à cette page. Importe ou sélectionne d’abord l’offre dans JobPilot.',
            ], 404);
        }

        $application = $resolved['application'];
        $job = $application->getJobOffer();
        $result = $this->suggestions->suggest(
            $job,
            $this->data->profile(),
            $question,
            $language,
            $maxLength,
        );

        return new JsonResponse([
            'schemaVersion' => 1,
            'applicationId' => $application->getId(),
            'jobOfferId' => $job->getId(),
            'matchedBy' => $resolved['matchedBy'],
            'question' => $question,
            'language' => $language,
            'maxLength' => $maxLength,
            'job' => [
                'title' => $job->getTitle(),
                'company' => $job->getCompany(),
            ],
            ...$result,
        ]);
    }
}

<?php
namespace App\Controller;

use App\Entity\JobOffer;
use App\Service\JobProcessor;
use App\Service\LocalDataService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ExtensionController
{
    public function __construct(private JobProcessor $processor, private LocalDataService $data) {}

    #[Route('/api/extension/import-page', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $job = (new JobOffer())->fill([
            'source' => $data['source'] ?? parse_url((string) ($data['url'] ?? ''), PHP_URL_HOST) ?: 'Extension Chrome',
            'sourceUrl' => $data['url'] ?? null,
            'title' => $data['title'] ?? 'Offre importée',
            'company' => $data['company'] ?? '',
            'location' => $data['location'] ?? '',
            'contractType' => $data['contractType'] ?? '',
            'workMode' => $data['workMode'] ?? '',
            'description' => mb_substr((string) ($data['text'] ?? ''), 0, 60000),
            'publishedAt' => $data['publishedAt'] ?? null,
            'rawData' => ['pageTitle' => $data['title'] ?? '', 'url' => $data['url'] ?? ''],
        ]);
        $this->processor->process($job, $this->data->settings(), $this->data->profile());
        return new JsonResponse($job->toArray(), 201);
    }
}

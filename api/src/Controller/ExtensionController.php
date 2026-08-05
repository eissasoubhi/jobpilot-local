<?php

declare(strict_types=1);

namespace App\Controller;

use App\JobCatalog\Application\CanonicalJobOfferService;
use App\Service\LocalDataService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ExtensionController
{
    public function __construct(
        private CanonicalJobOfferService $canonicalJobs,
        private LocalDataService $data,
    ) {
    }

    #[Route('/api/extension/import-page', methods: ['POST'])]
    public function import(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $url = trim((string) ($data['url'] ?? ''));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (
            $url === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array($scheme, ['http', 'https'], true)
            || $host === ''
        ) {
            throw new \InvalidArgumentException('L’URL de l’offre est invalide.');
        }

        $sourceCode = $this->sourceCode((string) ($data['sourceCode'] ?? ''), $host);
        $sourceName = trim((string) ($data['source'] ?? '')) ?: $this->sourceName($sourceCode, $host);
        $description = trim((string) ($data['description'] ?? $data['text'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '' || $description === '') {
            throw new \InvalidArgumentException('Le titre et la description de l’offre sont obligatoires.');
        }

        $payload = [
            'source' => $sourceName,
            'sourceCode' => $sourceCode,
            'sourceUrl' => $url,
            'externalId' => $this->externalId($data),
            'title' => $title,
            'company' => trim((string) ($data['company'] ?? '')),
            'location' => trim((string) ($data['location'] ?? '')),
            'contractType' => trim((string) ($data['contractType'] ?? '')),
            'workMode' => trim((string) ($data['workMode'] ?? '')),
            'description' => mb_substr($description, 0, 60000),
            'publishedAt' => $data['publishedAt'] ?? null,
            'salaryMin' => $this->nullableInt($data['salaryMin'] ?? null),
            'salaryMax' => $this->nullableInt($data['salaryMax'] ?? null),
            'tjmFixed' => $this->nullableInt($data['tjmFixed'] ?? null),
            'tjmMin' => $this->nullableInt($data['tjmMin'] ?? null),
            'tjmMax' => $this->nullableInt($data['tjmMax'] ?? null),
            'rawData' => [
                'pageTitle' => $title,
                'url' => $url,
                'extractionMethod' => trim((string) ($data['extractionMethod'] ?? 'visible-page')),
                'importedBy' => 'chrome-extension',
            ],
        ];

        $result = $this->canonicalJobs->import(
            $payload,
            $sourceCode,
            $sourceName,
            'EXTENSION',
            $this->data->settings(),
            $this->data->profile(),
        );

        return new JsonResponse(
            $result->job()->toArray(),
            $result->isImported() ? 201 : 200,
        );
    }

    /** @param array<string, mixed> $data */
    private function externalId(array $data): ?string
    {
        $externalId = trim((string) ($data['externalId'] ?? ''));

        return $externalId === '' ? null : mb_substr($externalId, 0, 180);
    }

    private function sourceCode(string $candidate, string $host): string
    {
        $candidate = strtolower(trim($candidate));
        if ($candidate === '') {
            $candidate = match (true) {
                str_contains($host, 'free-work.com') => 'free-work',
                str_contains($host, 'linkedin.com') => 'linkedin',
                str_contains($host, 'indeed.') => 'indeed',
                str_contains($host, 'apec.fr') => 'apec',
                str_contains($host, 'hellowork.com') => 'hellowork',
                str_contains($host, 'welcometothejungle.com') => 'welcome-to-the-jungle',
                str_contains($host, 'francetravail.fr') => 'france-travail',
                default => $host,
            };
        }

        $candidate = preg_replace('/[^a-z0-9]+/', '-', $candidate) ?? '';
        $candidate = trim($candidate, '-');

        return mb_substr($candidate !== '' ? $candidate : 'extension', 0, 64);
    }

    private function sourceName(string $sourceCode, string $host): string
    {
        return match ($sourceCode) {
            'free-work' => 'Free-Work',
            'linkedin' => 'LinkedIn',
            'indeed' => 'Indeed',
            'apec' => 'APEC',
            'hellowork' => 'Hellowork',
            'welcome-to-the-jungle' => 'Welcome to the Jungle',
            'france-travail' => 'France Travail',
            default => $host !== '' ? $host : 'Extension Chrome',
        };
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}

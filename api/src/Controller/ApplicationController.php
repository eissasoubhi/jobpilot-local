<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Entity\UserSettings;
use App\Service\ApplicationCvRepairService;
use App\Service\ApplicationMessageUpgradeService;
use App\Service\ApplicationMotivationRegenerator;
use App\Service\CoverLetterDocumentExporter;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\LocalDataService;
use App\Timeline\JobTimelineEventType;
use App\Timeline\JobTimelineRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/applications')]
final class ApplicationController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ApplicationCvRepairService $cvRepair,
        private ApplicationMessageUpgradeService $messageUpgrade,
        private LocalDataService $data,
        private JobProfileTechnologyComparisonService $technologyComparison,
        private CoverLetterDocumentExporter $coverLetterDocumentExporter,
        private JobTimelineRecorder $timeline,
        private ApplicationMotivationRegenerator $motivationRegenerator,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = $this->em->getRepository(Application::class)->findBy([], ['updatedAt' => 'DESC']);
        $this->cvRepair->repairAll($items);
        $this->messageUpgrade->upgradeLegacyMessages($items);
        $settings = $this->data->settings();

        return new JsonResponse(array_map(
            fn (Application $application): array => $this->serialize($application, $settings),
            $items,
        ));
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(Application $application, Request $request): JsonResponse
    {
        $previousStatus = $application->getStatus();
        $application->fill($request->toArray());

        if ($previousStatus !== 'SUBMITTED' && $application->getStatus() === 'SUBMITTED') {
            $this->timeline->record(
                $application->getJobOffer(),
                JobTimelineEventType::APPLICATION_SUBMITTED,
                ['previousStatus' => $previousStatus],
                $application,
                $application->getSubmittedAt(),
                'manual-status',
            );
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($application, $this->data->settings()));
    }

    #[Route('/{id}/message/regenerate', methods: ['POST'])]
    public function regenerateMessage(Application $application, Request $request): JsonResponse
    {
        try {
            $maxCharacters = $this->maxCharacters($request, 400, 50, 5_000);
            $message = $this->motivationRegenerator->message(
                $application->getJobOffer(),
                $this->data->profile(),
                $maxCharacters,
            );
            $application->regenerateMessage($message);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\LogicException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($application, $this->data->settings()));
    }

    #[Route('/{id}/cover-letter/regenerate', methods: ['POST'])]
    public function regenerateCoverLetter(Application $application, Request $request): JsonResponse
    {
        try {
            $maxCharacters = $this->maxCharacters($request, 1_500, 200, 20_000);
            $settings = $this->data->settings();
            $coverLetter = $this->motivationRegenerator->coverLetter(
                $application->getJobOffer(),
                $this->data->profile(),
                $settings->getSkills(),
                $maxCharacters,
            );
            $application->regenerateCoverLetter($coverLetter);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        } catch (\LogicException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($application, $this->data->settings()));
    }

    #[Route('/{id}/cover-letter', methods: ['PATCH'])]
    public function updateCoverLetter(Application $application, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (!array_key_exists('coverLetter', $payload) || !is_string($payload['coverLetter'])) {
            return new JsonResponse(['error' => 'Le texte de la lettre de motivation est obligatoire.'], 400);
        }

        try {
            $application->editCoverLetter($payload['coverLetter']);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($application, $this->data->settings()));
    }

    #[Route('/{id}/cover-letter/reset', methods: ['POST'])]
    public function resetCoverLetter(Application $application): JsonResponse
    {
        try {
            $application->resetCoverLetter();
        } catch (\LogicException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }

        $this->em->flush();

        return new JsonResponse($this->serialize($application, $this->data->settings()));
    }

    #[Route('/{id}/cover-letter/download', methods: ['GET'])]
    public function downloadCoverLetter(Application $application): Response
    {
        $letter = $application->getCoverLetter();
        if (trim($letter) === '') {
            return new JsonResponse(['error' => 'Aucune lettre de motivation n’est disponible.'], 404);
        }

        return $this->downloadResponse(
            $letter,
            'text/plain; charset=UTF-8',
            $this->coverLetterFilename($application, 'txt'),
        );
    }

    #[Route('/{id}/cover-letter/download/{format}', requirements: ['format' => 'pdf|docx'], methods: ['GET'])]
    public function downloadCoverLetterDocument(Application $application, string $format): Response
    {
        $letter = $application->getCoverLetter();
        if (trim($letter) === '') {
            return new JsonResponse(['error' => 'Aucune lettre de motivation n’est disponible.'], 404);
        }

        if ($format === 'pdf') {
            return $this->downloadResponse(
                $this->coverLetterDocumentExporter->pdf($letter),
                'application/pdf',
                $this->coverLetterFilename($application, 'pdf'),
            );
        }

        return $this->downloadResponse(
            $this->coverLetterDocumentExporter->docx($letter),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $this->coverLetterFilename($application, 'docx'),
        );
    }

    /** @return array<string, mixed> */
    private function serialize(Application $application, UserSettings $settings): array
    {
        return [
            ...$application->toArray(),
            'profileComparison' => $this->technologyComparison->compare($application->getJobOffer(), $settings),
        ];
    }

    private function maxCharacters(Request $request, int $default, int $min, int $max): int
    {
        $payload = $request->toArray();
        if (!array_key_exists('maxCharacters', $payload)) {
            return $default;
        }

        $raw = $payload['maxCharacters'];
        if (is_int($raw)) {
            $value = $raw;
        } elseif (is_string($raw) && ctype_digit($raw)) {
            $value = (int) $raw;
        } else {
            throw new \InvalidArgumentException('La longueur maximale doit être un nombre entier de caractères.');
        }

        if ($value < $min || $value > $max) {
            throw new \InvalidArgumentException(sprintf(
                'La longueur maximale doit être comprise entre %d et %d caractères.',
                $min,
                $max,
            ));
        }

        return $value;
    }

    private function downloadResponse(string $content, string $contentType, string $filename): Response
    {
        $disposition = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        );

        return new Response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function coverLetterFilename(Application $application, string $extension): string
    {
        $job = $application->getJobOffer();
        $parts = array_filter([
            'Lettre-motivation',
            trim($job->getCompany()),
            trim($job->getTitle()),
        ], static fn (string $part): bool => $part !== '');
        $name = implode('_', $parts);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = $ascii === false ? $name : $ascii;
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'Lettre-motivation';
        $name = trim($name, '-_.');
        if ($name === '') {
            $name = 'Lettre-motivation';
        }

        return substr($name, 0, 160).'.'.$extension;
    }
}

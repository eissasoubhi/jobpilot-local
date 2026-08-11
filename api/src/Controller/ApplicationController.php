<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Entity\UserSettings;
use App\Service\ApplicationCvRepairService;
use App\Service\ApplicationMessageUpgradeService;
use App\Service\JobProfileTechnologyComparisonService;
use App\Service\LocalDataService;
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
        $application->fill($request->toArray());
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

        $filename = $this->coverLetterFilename($application);
        $disposition = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        );

        return new Response($letter, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(Application $application, UserSettings $settings): array
    {
        return [
            ...$application->toArray(),
            'profileComparison' => $this->technologyComparison->compare($application->getJobOffer(), $settings),
        ];
    }

    private function coverLetterFilename(Application $application): string
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

        return substr($name, 0, 160).'.txt';
    }
}

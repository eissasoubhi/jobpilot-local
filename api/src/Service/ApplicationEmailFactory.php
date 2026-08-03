<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;

final class ApplicationEmailFactory
{
    public function __construct(private CvStorage $cvStorage) {}

    /**
     * @return array{
     *     subject: string,
     *     body: string,
     *     attachments: list<array{path: string, filename: string, mimeType: string}>,
     *     attachmentNames: list<string>
     * }
     */
    public function create(Application $application): array
    {
        $job = $application->getJobOffer();
        $cv = $application->getCvDocument();

        if ($cv === null) {
            throw new \RuntimeException('Aucun CV n’est sélectionné pour cette candidature.');
        }

        $cvPath = $this->cvStorage->path($cv->getStoredName());
        if (!is_file($cvPath) || !is_readable($cvPath)) {
            throw new \RuntimeException(
                sprintf(
                    'Le fichier du CV « %s » est introuvable dans le stockage privé. Téléverse de nouveau ce CV avant le test.',
                    $cv->getOriginalName(),
                ),
            );
        }

        $parts = [trim($application->getMessage()), trim($application->getCoverLetter())];
        $compensation = $application->getCompensationAnswer();

        if ($compensation !== null && trim($compensation) !== '') {
            $parts[] = ($job->getLanguage() === 'en' ? 'Compensation: ' : 'Rémunération : ').$compensation;
        }

        return [
            'subject' => $job->getLanguage() === 'en'
                ? 'Application – '.$job->getTitle()
                : 'Candidature – '.$job->getTitle(),
            'body' => implode("\n\n---\n\n", array_values(array_filter(
                $parts,
                static fn (string $part): bool => $part !== '',
            ))),
            'attachments' => [[
                'path' => $cvPath,
                'filename' => $cv->getOriginalName(),
                'mimeType' => $cv->getMimeType(),
            ]],
            'attachmentNames' => [$cv->getOriginalName()],
        ];
    }
}

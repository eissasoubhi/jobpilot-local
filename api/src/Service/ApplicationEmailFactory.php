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

        $message = trim($application->getMessage());
        if ($message === '') {
            throw new \RuntimeException('Le message de candidature est vide. Prépare de nouveau la candidature.');
        }

        return [
            'subject' => $job->getLanguage() === 'en'
                ? 'Application – '.$job->getTitle()
                : 'Candidature – '.$job->getTitle(),
            // The cover letter remains stored on the application for optional manual use.
            // It is deliberately not concatenated with the email body.
            'body' => $this->withCompensation(
                $message,
                $application->getCompensationAnswer(),
                $job->getLanguage(),
            ),
            'attachments' => [[
                'path' => $cvPath,
                'filename' => $cv->getOriginalName(),
                'mimeType' => $cv->getMimeType(),
            ]],
            'attachmentNames' => [$cv->getOriginalName()],
        ];
    }

    private function withCompensation(string $message, ?string $compensation, string $language): string
    {
        $compensation = trim((string) $compensation);
        if ($compensation === '') {
            return $message;
        }

        $compensation = rtrim($compensation, " .\t\n\r\0\x0B");
        $paragraph = $language === 'en'
            ? 'Regarding compensation, my expectation is '.$compensation.'.'
            : 'Concernant la rémunération, ma proposition est de '.$compensation.'.';
        $closings = $language === 'en'
            ? ["\n\nBest regards,", "\n\nKind regards,"]
            : ["\n\nBien cordialement,", "\n\nCordialement,"];

        foreach ($closings as $closing) {
            $position = strrpos($message, $closing);
            if ($position === false) {
                continue;
            }

            return substr($message, 0, $position)
                ."\n\n".$paragraph
                .substr($message, $position);
        }

        return $message."\n\n".$paragraph;
    }
}

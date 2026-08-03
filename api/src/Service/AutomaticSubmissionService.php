<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;

final class AutomaticSubmissionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private GmailService $gmail,
        private CvStorage $cvStorage,
    ) {}

    /**
     * @return array{status: string, reason?: string, gmailMessageId?: string}
     */
    public function submitIfEligible(Application $application, UserSettings $settings): array
    {
        $job = $application->getJobOffer();

        if (!$settings->isAutoSubmitEnabled()) {
            return ['status' => 'skipped', 'reason' => 'disabled'];
        }

        if ($job->getScore() < $settings->getAutoSubmitThreshold()) {
            return ['status' => 'skipped', 'reason' => 'score_below_threshold'];
        }

        if ($application->getStatus() !== 'READY_TO_SUBMIT') {
            return ['status' => 'skipped', 'reason' => 'not_ready'];
        }

        if ($application->getSubmittedAt() !== null || $application->getGmailMessageId() !== null) {
            return ['status' => 'skipped', 'reason' => 'already_submitted'];
        }

        $recipient = $job->getApplicationEmail();
        if ($recipient === null) {
            return ['status' => 'skipped', 'reason' => 'missing_application_email'];
        }

        $cv = $application->getCvDocument();
        if ($cv === null) {
            return ['status' => 'skipped', 'reason' => 'missing_cv'];
        }

        if (!$this->gmail->hasSendPermission()) {
            return ['status' => 'skipped', 'reason' => 'gmail_send_permission_missing'];
        }

        if ($this->submittedToday() >= $settings->getAutoSubmitDailyLimit()) {
            return ['status' => 'skipped', 'reason' => 'daily_limit_reached'];
        }

        $application->markSubmissionAttempt();
        $this->em->flush();

        try {
            $result = $this->gmail->sendEmail(
                $recipient,
                $this->subject($application),
                $this->body($application),
                [[
                    'path' => $this->cvStorage->path($cv->getStoredName()),
                    'filename' => $cv->getOriginalName(),
                    'mimeType' => $cv->getMimeType(),
                ]],
            );
            $application->markSubmittedAutomatically($result['id']);
            $this->em->flush();

            return ['status' => 'submitted', 'gmailMessageId' => $result['id']];
        } catch (\Throwable $error) {
            $application->markSubmissionFailed($error->getMessage());
            $this->em->flush();

            return ['status' => 'failed', 'reason' => $error->getMessage()];
        }
    }

    private function submittedToday(): int
    {
        $start = new \DateTimeImmutable('today');

        return (int) $this->em->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->andWhere('application.channel = :channel')
            ->andWhere('application.submittedAt >= :start')
            ->setParameter('channel', 'Gmail automatique')
            ->setParameter('start', $start)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function subject(Application $application): string
    {
        $job = $application->getJobOffer();

        return $job->getLanguage() === 'en'
            ? 'Application – '.$job->getTitle()
            : 'Candidature – '.$job->getTitle();
    }

    private function body(Application $application): string
    {
        $parts = [trim($application->getMessage()), trim($application->getCoverLetter())];
        $compensation = $application->getCompensationAnswer();

        if ($compensation !== null && trim($compensation) !== '') {
            $parts[] = ($application->getJobOffer()->getLanguage() === 'en' ? 'Compensation: ' : 'Rémunération : ').$compensation;
        }

        return implode("\n\n---\n\n", array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
    }
}

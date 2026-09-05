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
        private ApplicationEmailFactory $emailFactory,
        private RequiredPrimaryTechnologyGuard $requiredTechnologyGuard,
        private SearchPreferenceMatcher $searchPreferences,
        private LocalDataService $data,
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

        if ($job->getStatus() !== 'PREPARED') {
            return ['status' => 'skipped', 'reason' => 'job_not_prepared'];
        }

        if (!$this->searchPreferences->evaluate($job, $this->data->profile())['eligible']) {
            return ['status' => 'skipped', 'reason' => 'profile_preferences_mismatch'];
        }

        if ($this->requiredTechnologyGuard->evaluate($job, $settings)['hardRejected']) {
            return ['status' => 'skipped', 'reason' => 'required_primary_stack_missing'];
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

        if ($application->getCvDocument() === null) {
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
            $email = $this->emailFactory->create($application);
            $result = $this->gmail->sendEmail(
                $recipient,
                $email['subject'],
                $email['body'],
                $email['attachments'],
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
}

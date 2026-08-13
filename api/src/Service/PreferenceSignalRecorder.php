<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\PreferenceSignal;
use Doctrine\ORM\EntityManagerInterface;

final class PreferenceSignalRecorder
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function recordApplicationStatusTransition(Application $application, string $previousStatus): ?PreferenceSignal
    {
        $currentStatus = $application->getStatus();
        if ($currentStatus === $previousStatus) {
            return null;
        }

        $definition = match ($currentStatus) {
            'IGNORED_NOT_MATCH' => ['JOB_REJECTED_BY_USER', -1, PreferenceSignal::ORIGIN_USER_DECISION],
            'SUBMITTED' => ['APPLICATION_SUBMITTED', 1, PreferenceSignal::ORIGIN_USER_DECISION],
            'RESPONSE_RECEIVED', 'INFORMATION_REQUESTED' => ['RECRUITER_RESPONSE', 1, PreferenceSignal::ORIGIN_PIPELINE_OUTCOME],
            'INTERVIEW' => ['INTERVIEW_REACHED', 1, PreferenceSignal::ORIGIN_PIPELINE_OUTCOME],
            // A recruiter rejection is useful outcome data, but it is deliberately neutral for preference learning.
            'REJECTED' => ['RECRUITER_REJECTION', 0, PreferenceSignal::ORIGIN_PIPELINE_OUTCOME],
            default => null,
        };

        if ($definition === null) {
            return null;
        }

        [$type, $value, $origin] = $definition;
        $signal = new PreferenceSignal(
            $application,
            $type,
            $value,
            $origin,
            [
                'previousStatus' => $previousStatus,
                'currentStatus' => $currentStatus,
            ],
        );
        $this->em->persist($signal);

        return $signal;
    }
}

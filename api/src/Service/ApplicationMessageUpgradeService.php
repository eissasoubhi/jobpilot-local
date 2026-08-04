<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;

final class ApplicationMessageUpgradeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocalDataService $data,
        private ApplicationMessageBuilder $builder,
    ) {}

    /**
     * @param iterable<Application> $applications
     */
    public function upgradeLegacyMessages(iterable $applications): int
    {
        $profile = $this->data->profile();
        $profileData = $profile->toArray();
        if (trim((string) $profileData['fullName']) === '' || (int) $profileData['yearsOfExperience'] <= 0) {
            return 0;
        }

        $updated = 0;
        foreach ($applications as $application) {
            if (!in_array($application->getStatus(), ['READY_TO_SUBMIT', 'MISSING_CV'], true)) {
                continue;
            }

            if (!$this->isLegacyGeneratedMessage($application->getMessage())) {
                continue;
            }

            $content = $this->builder->build($application->getJobOffer(), $profile);
            $application->prepare(
                $application->getCvDocument(),
                $content['message'],
                $content['coverLetter'],
                $application->getCompensationAnswer(),
            );
            ++$updated;
        }

        if ($updated > 0) {
            $this->em->flush();
        }

        return $updated;
    }

    private function isLegacyGeneratedMessage(string $message): bool
    {
        $message = ltrim($message);

        return str_starts_with($message, "Bonjour,\n\nJe suis intéressé par le poste de ")
            || str_starts_with($message, "Hello,\n\nI am interested in the ");
    }
}

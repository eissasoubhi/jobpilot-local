<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;

final class JobProcessor
{
    public function __construct(
        private LanguageDetector $languageDetector,
        private MatchingScoreService $matching,
        private TjmCalculator $tjmCalculator,
        private SalaryExpectationCalculator $salaryCalculator,
        private CvSelector $cvSelector,
        private ApplicationEmailExtractor $emailExtractor,
        private ApplicationPreparationService $preparation,
        private EntityManagerInterface $em,
    ) {}

    public function process(JobOffer $job, UserSettings $settings, CandidateProfile $profile): void
    {
        $language = $this->languageDetector->detect($job->getTitle().' '.$job->getDescription());
        $evaluation = $this->matching->evaluate($job, $settings);
        $reasons = $evaluation['reasons'];
        $hardRejected = $evaluation['hardRejected'];

        if ($job->getApplicationEmail() === null) {
            $job->setApplicationEmail($this->emailExtractor->extract($job->getTitle().' '.$job->getDescription()));
        }

        $isFreelance = preg_match('/freelance|mission|portage|sous-traitance/i', $job->getContractType()) === 1;
        $hasAdvertisedTjm = $job->getTjmFixed() !== null || ($job->getTjmMin() !== null && $job->getTjmMax() !== null);
        $proposedTjm = $isFreelance
            ? $this->tjmCalculator->calculate($job->getTjmFixed(), $job->getTjmMin(), $job->getTjmMax(), $job->getLocation(), $job->getWorkMode(), $settings, false)
            : null;

        if ($isFreelance && $hasAdvertisedTjm && $proposedTjm === null) {
            $hardRejected = true;
            $reasons[] = 'TJM annoncé inférieur au minimum freelance configuré.';
        }

        $salary = $this->salaryCalculator->calculate($job->getContractType(), $job->getSalaryMin(), $job->getSalaryMax(), $settings);
        if (!$salary['eligible']) {
            $hardRejected = true;
            $reasons[] = $salary['reason'];
        }

        $cv = $this->cvSelector->select($language, $job->getTitle().' '.$job->getDescription());
        $status = $hardRejected ? 'REJECTED_BY_FILTER' : 'PREPARED';

        $job->setEvaluation($language, $evaluation['score'], $reasons, $proposedTjm, $salary['proposed'], $status, $cv);
        $this->em->persist($job);
        $this->em->flush();

        if ($status === 'PREPARED') {
            $this->preparation->prepare($job, $profile);
        }
    }
}

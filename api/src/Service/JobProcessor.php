<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Entity\UserSettings;
use Doctrine\ORM\EntityManagerInterface;

final class JobProcessor
{
    public function __construct(
        private LanguageDetector $languageDetector,
        private MatchingScoreService $matching,
        private SearchPreferenceMatcher $searchPreferences,
        private TjmCalculator $tjmCalculator,
        private SalaryExpectationCalculator $salaryCalculator,
        private CvSelector $cvSelector,
        private ApplicationEmailExtractor $emailExtractor,
        private ApplicationPreparationService $preparation,
        private RequiredPrimaryTechnologyGuard $requiredTechnologyGuard,
        private MatchingScoreVersionStore $matchingScoreVersionStore,
        private EntityManagerInterface $em,
    ) {}

    public function process(JobOffer $job, UserSettings $settings, CandidateProfile $profile): void
    {
        $language = $this->languageDetector->detect($job->getTitle().' '.$job->getDescription());
        $preferenceEvaluation = $this->searchPreferences->evaluate($job, $profile);
        if (!$preferenceEvaluation['eligible']) {
            $this->rejectForSearchPreferences($job, $language, $preferenceEvaluation['reasons']);
            return;
        }

        $evaluation = $this->matching->evaluate($job, $settings);
        $score = (int) $evaluation['score'];
        $reasons = $evaluation['reasons'];
        $hardRejected = $evaluation['hardRejected'];

        $requiredTechnology = $this->requiredTechnologyGuard->evaluate($job, $settings);
        if ($requiredTechnology['hardRejected']) {
            $hardRejected = true;
            if ($requiredTechnology['scoreCap'] !== null) {
                $score = min($score, $requiredTechnology['scoreCap']);
            }
            foreach ($requiredTechnology['reasons'] as $reason) {
                if (!in_array($reason, $reasons, true)) {
                    $reasons[] = $reason;
                }
            }
        }

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

        $job->setEvaluation($language, $score, $reasons, $proposedTjm, $salary['proposed'], $status, $cv);
        $this->em->persist($job);
        $this->matchingScoreVersionStore->mark($job);
        $this->em->flush();

        if ($status === 'PREPARED') {
            $this->preparation->prepare($job, $profile);
        }
    }

    public function refreshSearchPreferences(JobOffer $job, UserSettings $settings, CandidateProfile $profile): void
    {
        $application = $this->em->getRepository(Application::class)->findOneBy(['jobOffer' => $job]);
        if ($application instanceof Application && (
            $application->getSubmittedAt() !== null
            || $application->getStatus() === 'SUBMISSION_PENDING'
        )) {
            return;
        }

        $wasPreferenceRejected = $this->searchPreferences->isPreferenceRejection($job);
        $preferenceEvaluation = $this->searchPreferences->evaluate($job, $profile);

        if (!$preferenceEvaluation['eligible']) {
            if (!$wasPreferenceRejected || $job->getStatus() !== 'REJECTED_BY_FILTER') {
                $language = $this->languageDetector->detect($job->getTitle().' '.$job->getDescription());
                $this->rejectForSearchPreferences($job, $language, $preferenceEvaluation['reasons']);
            }
            return;
        }

        if ($wasPreferenceRejected) {
            $this->process($job, $settings, $profile);
        }
    }

    /** @param list<string> $reasons */
    private function rejectForSearchPreferences(JobOffer $job, string $language, array $reasons): void
    {
        $job->setEvaluation($language, 0, $reasons, null, null, 'REJECTED_BY_FILTER', null);
        $this->em->persist($job);
        $this->matchingScoreVersionStore->mark($job);
        $this->em->flush();
    }
}

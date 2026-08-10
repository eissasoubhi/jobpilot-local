<?php

declare(strict_types=1);

namespace App\JobCatalog\Application;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use App\Entity\UserSettings;
use App\Service\Ai\AiOfferIntakeFilter;
use App\Service\JobProcessor;
use Doctrine\ORM\EntityManagerInterface;

final class CanonicalJobOfferService
{
    private ContaminatedJobDescriptionRepairer $descriptionRepairer;

    public function __construct(
        private EntityManagerInterface $em,
        private CanonicalJobMatcher $matcher,
        private JobProcessor $processor,
        private AiOfferIntakeFilter $intakeFilter,
        ?ContaminatedJobDescriptionRepairer $descriptionRepairer = null,
    ) {
        $this->descriptionRepairer = $descriptionRepairer ?? new ContaminatedJobDescriptionRepairer();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function import(
        array $payload,
        string $sourceCode,
        string $sourceName,
        string $mode,
        UserSettings $settings,
        CandidateProfile $profile,
        bool $filterNewOfferByProfile = false,
    ): CanonicalJobImportResult {
        $sourceCode = $this->normalizeSourceCode($sourceCode, $sourceName);
        $sourceName = trim($sourceName) !== '' ? trim($sourceName) : $sourceCode;
        $externalId = $this->externalId($payload, $sourceCode);
        $normalizedUrl = $this->matcher->normalizeUrl((string) ($payload['sourceUrl'] ?? ''));

        $payload['source'] = $sourceName;
        $payload['sourceCode'] = $sourceCode;
        $payload['externalId'] = $externalId;
        $payload['normalizedUrl'] = $normalizedUrl;
        $payload['rawData'] = [
            'connector' => [
                'code' => $sourceCode,
                'mode' => $mode,
            ],
            'payload' => is_array($payload['rawData'] ?? null) ? $payload['rawData'] : [],
        ];

        $existingOccurrence = $this->em->getRepository(JobSourceOccurrence::class)->findOneBy([
            'sourceCode' => $sourceCode,
            'externalId' => $externalId,
        ]);
        if ($existingOccurrence instanceof JobSourceOccurrence) {
            $job = $existingOccurrence->getJobOffer();
            $existingOccurrence->touch($payload);
            $descriptionRepaired = $this->descriptionRepairer->repair($job, $payload, $sourceCode);
            $job->enrichFromOccurrence($payload);

            if ($descriptionRepaired && $this->canReprocessAutomatically($job)) {
                $this->processor->process($job, $settings, $profile);
            } else {
                $this->em->flush();
            }

            return new CanonicalJobImportResult(
                $job,
                $existingOccurrence,
                CanonicalJobImportResult::DUPLICATE,
                'EXACT_SOURCE_ID',
                100,
                $descriptionRepaired
                    ? ['Même identifiant externe déjà importé ; description Gmail polluée réparée depuis la nouvelle occurrence.']
                    : ['Même identifiant externe déjà importé pour cette source.'],
            );
        }

        $match = $this->matcher->find($payload);
        if ($match !== null) {
            $job = $match['job'];
            $occurrence = new JobSourceOccurrence($job, $sourceCode, $sourceName, $externalId);
            $occurrence->refresh(
                $payload,
                $match['matchType'],
                $match['score'],
                $match['reasons'],
            );
            $job->enrichFromOccurrence($payload);
            $this->em->persist($occurrence);
            $this->em->persist($job);
            $this->em->flush();

            return new CanonicalJobImportResult(
                $job,
                $occurrence,
                CanonicalJobImportResult::MERGED,
                $match['matchType'],
                $match['score'],
                $match['reasons'],
            );
        }

        $job = (new JobOffer())->fill($payload);
        if ($job->getTitle() === '' || $job->getDescription() === '') {
            throw new \InvalidArgumentException('Offre sans titre ou description.');
        }

        if ($filterNewOfferByProfile) {
            $rejection = $this->intakeFilter->rejection($job, $settings);
            if ($rejection !== null) {
                throw new ProfileFilteredJobOffer($rejection->score, $rejection->confidence);
            }
        }

        $this->processor->process($job, $settings, $profile);
        $occurrence = new JobSourceOccurrence($job, $sourceCode, $sourceName, $externalId);
        $occurrence->refresh(
            $payload,
            'PRIMARY',
            100,
            ['Première occurrence de cette offre canonique.'],
        );
        $this->em->persist($occurrence);
        $this->em->flush();

        return new CanonicalJobImportResult(
            $job,
            $occurrence,
            CanonicalJobImportResult::IMPORTED,
            'PRIMARY',
            100,
            ['Première occurrence de cette offre canonique.'],
        );
    }

    private function canReprocessAutomatically(JobOffer $job): bool
    {
        return in_array($job->getStatus(), ['DISCOVERED', 'PREPARED', 'REJECTED_BY_FILTER'], true);
    }

    /** @param array<string, mixed> $payload */
    private function externalId(array $payload, string $sourceCode): string
    {
        $externalId = trim((string) ($payload['externalId'] ?? ''));
        if ($externalId !== '') {
            return mb_substr($externalId, 0, 180);
        }

        $normalizedUrl = $this->matcher->normalizeUrl((string) ($payload['sourceUrl'] ?? ''));
        if ($normalizedUrl !== null) {
            return mb_substr('url-'.hash('sha256', $normalizedUrl), 0, 180);
        }

        $fingerprint = implode('|', [
            $sourceCode,
            trim((string) ($payload['title'] ?? '')),
            trim((string) ($payload['company'] ?? '')),
            trim((string) ($payload['publishedAt'] ?? '')),
            bin2hex(random_bytes(8)),
        ]);

        return mb_substr('generated-'.hash('sha256', $fingerprint), 0, 180);
    }

    private function normalizeSourceCode(string $sourceCode, string $sourceName): string
    {
        $value = strtolower(trim($sourceCode));
        if ($value === '') {
            $value = strtolower(trim($sourceName));
        }
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return mb_substr($value !== '' ? $value : 'manual', 0, 64);
    }
}

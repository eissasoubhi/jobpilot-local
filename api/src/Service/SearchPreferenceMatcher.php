<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

final class SearchPreferenceMatcher
{
    private const REASON_PREFIX = 'Préférence de recherche : ';

    /** @return array{eligible: bool, reasons: list<string>} */
    public function evaluate(JobOffer $job, CandidateProfile $profile): array
    {
        $profileData = $profile->toArray();
        $reasons = [];

        $acceptedContracts = is_array($profileData['acceptedContracts'] ?? null)
            ? array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $profileData['acceptedContracts'],
            )))
            : [];

        if ($acceptedContracts !== [] && !$this->contractAllowed($job->getContractType(), $acceptedContracts)) {
            $reasons[] = self::REASON_PREFIX.sprintf(
                'contrat %s hors critères (%s).',
                $job->getContractType() !== '' ? $job->getContractType() : 'inconnu',
                implode(', ', $acceptedContracts),
            );
        }

        $workModePreference = trim((string) ($profileData['workModePreference'] ?? ''));
        if (!$this->workModeAllowed($job->getWorkMode(), $workModePreference)) {
            $reasons[] = self::REASON_PREFIX.sprintf(
                'mode de travail %s hors critère (%s).',
                $job->getWorkMode() !== '' ? $job->getWorkMode() : 'inconnu',
                $workModePreference,
            );
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function isFreelanceContract(string $contractType): bool
    {
        return $this->canonicalContract($contractType) === 'FREELANCE';
    }

    public function isPreferenceRejection(JobOffer $job): bool
    {
        $reasons = $job->toArray()['scoreReasons'] ?? [];
        if (!is_array($reasons)) {
            return false;
        }

        foreach ($reasons as $reason) {
            if (is_string($reason) && str_starts_with($reason, self::REASON_PREFIX)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $acceptedContracts */
    private function contractAllowed(string $jobContract, array $acceptedContracts): bool
    {
        $jobCanonical = $this->canonicalContract($jobContract);
        $jobNormalized = $this->normalize($jobContract);

        foreach ($acceptedContracts as $acceptedContract) {
            $acceptedCanonical = $this->canonicalContract($acceptedContract);
            if ($jobCanonical !== 'OTHER' && $acceptedCanonical === $jobCanonical) {
                return true;
            }

            if ($jobNormalized !== '' && $jobNormalized === $this->normalize($acceptedContract)) {
                return true;
            }
        }

        return false;
    }

    private function canonicalContract(string $value): string
    {
        $value = $this->normalize($value);

        if (preg_match('/\b(cdi|contrat a duree indeterminee)\b/u', $value) === 1) {
            return 'CDI';
        }
        if (preg_match('/\b(cdd|contrat a duree determinee)\b/u', $value) === 1) {
            return 'CDD';
        }
        if (preg_match('/freelance|independant|independent|contractor|self employed|\bb2b\b|mission|portage|sous traitance|prestation|non salarie/u', $value) === 1) {
            return 'FREELANCE';
        }
        if (preg_match('/alternance|apprentissage/u', $value) === 1) {
            return 'ALTERNANCE';
        }
        if (preg_match('/\bstage\b/u', $value) === 1) {
            return 'STAGE';
        }

        return 'OTHER';
    }

    private function workModeAllowed(string $jobWorkMode, string $preference): bool
    {
        $allowedModes = $this->allowedWorkModes($preference);
        if ($allowedModes === []) {
            return true;
        }

        $jobMode = $this->canonicalWorkMode($jobWorkMode);
        if ($jobMode === 'UNKNOWN') {
            return true;
        }

        return in_array($jobMode, $allowedModes, true);
    }

    /** @return list<string> */
    private function allowedWorkModes(string $preference): array
    {
        $normalized = $this->normalize($preference);
        if ($normalized === '' || preg_match('/aucune preference|tous|tout mode|indifferent|indifferente|all/u', $normalized) === 1) {
            return [];
        }

        $mentionsRemote = preg_match('/teletravail|remote|distance/u', $normalized) === 1;
        $mentionsHybrid = preg_match('/hybride|hybrid/u', $normalized) === 1;
        $mentionsOnsite = preg_match('/sur site|presentiel|on site|onsite/u', $normalized) === 1;

        if ($mentionsRemote && $mentionsHybrid) {
            return ['REMOTE', 'HYBRID'];
        }
        if ($mentionsRemote) {
            return ['REMOTE'];
        }
        if ($mentionsHybrid) {
            return ['HYBRID'];
        }
        if ($mentionsOnsite) {
            return ['ONSITE'];
        }

        return [];
    }

    private function canonicalWorkMode(string $value): string
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return 'UNKNOWN';
        }
        if (preg_match('/hybride|hybrid/u', $normalized) === 1) {
            return 'HYBRID';
        }
        if (preg_match('/teletravail total|full remote|100 ?% remote|remote only|teletravail|remote|distance/u', $normalized) === 1) {
            return 'REMOTE';
        }
        if (preg_match('/sur site|presentiel|on site|onsite/u', $normalized) === 1) {
            return 'ONSITE';
        }

        return 'UNKNOWN';
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = strtolower($transliterated);
        }

        return trim(preg_replace('/[^a-z0-9%]+/', ' ', $value) ?? '');
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;

/**
 * Resolves the company that should be named in candidate-facing motivation text.
 *
 * Job-board/import metadata can occasionally put the platform name (Indeed,
 * LinkedIn, etc.) in JobOffer::company. That value must not leak into a cover
 * letter as if the platform were the employer. A user-provided override always
 * wins; otherwise clientName is preferred, then company, unless the candidate
 * looks like the acquisition platform/source.
 */
final class TargetCompanyName
{
    public static function resolve(JobOffer $job, ?string $override = null): string
    {
        if ($override !== null) {
            return self::clean($override);
        }

        $client = self::clean($job->getClientName() ?? '');
        if ($client !== '' && !self::looksLikeSourcePlatform($client, $job)) {
            return $client;
        }

        $company = self::clean($job->getCompany());
        if ($company !== '' && !self::looksLikeSourcePlatform($company, $job)) {
            return $company;
        }

        return '';
    }

    private static function looksLikeSourcePlatform(string $candidate, JobOffer $job): bool
    {
        $candidateNormalized = self::normalize($candidate);
        if ($candidateNormalized === '') {
            return false;
        }

        $sourceNormalized = self::normalize($job->getSource());
        if ($sourceNormalized !== '' && $candidateNormalized === $sourceNormalized) {
            return true;
        }

        $sourceCodeNormalized = self::normalize($job->getSourceCode() ?? '');
        if (mb_strlen($candidateNormalized) >= 4
            && $sourceCodeNormalized !== ''
            && (str_contains($sourceCodeNormalized, $candidateNormalized)
                || str_contains($candidateNormalized, $sourceCodeNormalized))) {
            return true;
        }

        $host = parse_url((string) ($job->getSourceUrl() ?? ''), PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $candidateCompact = preg_replace('/[^a-z0-9]+/', '', $candidateNormalized) ?? '';
            $hostCompact = preg_replace('/[^a-z0-9]+/', '', mb_strtolower($host)) ?? '';
            if (mb_strlen($candidateCompact) >= 4 && str_contains($hostCompact, $candidateCompact)) {
                return true;
            }
        }

        return false;
    }

    private static function clean(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return mb_substr($value, 0, 160);
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(self::clean($value));

        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '');
    }
}

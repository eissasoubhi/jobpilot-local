<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Application;
use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use Doctrine\ORM\EntityManagerInterface;

final class ExtensionApplicationDocumentResolver
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array{application: Application, matchedBy: string}|null
     */
    public function resolve(array $criteria): ?array
    {
        $applicationId = max(0, (int) ($criteria['applicationId'] ?? 0));
        if ($applicationId > 0) {
            $application = $this->em->find(Application::class, $applicationId);
            if ($application instanceof Application) {
                return ['application' => $application, 'matchedBy' => 'application-id'];
            }
        }

        $jobOfferId = max(0, (int) ($criteria['jobOfferId'] ?? 0));
        if ($jobOfferId > 0) {
            $jobOffer = $this->em->find(JobOffer::class, $jobOfferId);
            if ($jobOffer instanceof JobOffer) {
                $application = $this->applicationForJob($jobOffer);
                if ($application instanceof Application) {
                    return ['application' => $application, 'matchedBy' => 'job-offer-id'];
                }
            }
        }

        $url = $this->normalizeUrl((string) ($criteria['url'] ?? ''));
        if ($url === null) {
            return null;
        }

        $jobOffer = $this->findJobByUrl($url);
        if (!$jobOffer instanceof JobOffer) {
            return null;
        }

        $application = $this->applicationForJob($jobOffer);
        if (!$application instanceof Application) {
            return null;
        }

        return ['application' => $application, 'matchedBy' => 'source-url'];
    }

    private function applicationForJob(JobOffer $jobOffer): ?Application
    {
        $applications = $this->em->getRepository(Application::class)->findBy(
            ['jobOffer' => $jobOffer],
            ['updatedAt' => 'DESC'],
            2,
        );

        // A job should normally have one prepared application. If the data is ever
        // inconsistent, refusing an ambiguous match is safer than uploading the
        // wrong CV or cover letter.
        return count($applications) === 1 ? $applications[0] : null;
    }

    private function findJobByUrl(string $normalizedUrl): ?JobOffer
    {
        $variants = $this->urlVariants($normalizedUrl);
        foreach ($this->em->getRepository(JobOffer::class)->findBy(['sourceUrl' => $variants]) as $jobOffer) {
            if ($jobOffer instanceof JobOffer && $this->normalizeUrl((string) $jobOffer->getSourceUrl()) === $normalizedUrl) {
                return $jobOffer;
            }
        }

        $repository = $this->em->getRepository(JobSourceOccurrence::class);
        $occurrences = [
            ...$repository->findBy(['sourceUrl' => $variants]),
            ...$repository->findBy(['normalizedUrl' => $variants]),
        ];

        foreach ($occurrences as $occurrence) {
            if (!$occurrence instanceof JobSourceOccurrence) {
                continue;
            }

            $sourceUrl = $this->normalizeUrl((string) $occurrence->getSourceUrl());
            $occurrenceUrl = $this->normalizeUrl((string) $occurrence->getNormalizedUrl());
            if ($sourceUrl === $normalizedUrl || $occurrenceUrl === $normalizedUrl) {
                return $occurrence->getJobOffer();
            }
        }

        return null;
    }

    /** @return list<string> */
    private function urlVariants(string $normalizedUrl): array
    {
        return array_values(array_unique([
            $normalizedUrl,
            rtrim($normalizedUrl, '/'),
            rtrim($normalizedUrl, '/').'/',
        ]));
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');

        $normalized = $scheme.'://'.$host;
        if (is_int($port) && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $normalized .= ':'.$port;
        }
        $normalized .= '/'.ltrim($path, '/');
        $normalized = rtrim($normalized, '/');
        if ($query !== '') {
            parse_str($query, $params);
            ksort($params);
            $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            if ($query !== '') {
                $normalized .= '?'.$query;
            }
        }

        return $normalized;
    }
}

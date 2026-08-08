<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\UserSettings;

final class JobProfileTechnologyComparisonService
{
    /** @var array<string, list<string>> */
    private const TECHNOLOGY_ALIASES = [
        'PHP' => ['php'],
        'Symfony' => ['symfony'],
        'Laravel' => ['laravel'],
        'React' => ['react', 'react.js', 'reactjs'],
        'Next.js' => ['next.js', 'nextjs', 'next js'],
        'TypeScript' => ['typescript'],
        'JavaScript' => ['javascript'],
        'Vue.js' => ['vue.js', 'vuejs', 'vue 3', 'vue3'],
        'Nuxt' => ['nuxt', 'nuxt.js', 'nuxtjs'],
        'Doctrine' => ['doctrine'],
        'API Platform' => ['api platform'],
        'Drupal' => ['drupal'],
        'WordPress' => ['wordpress'],
        'MySQL' => ['mysql'],
        'MariaDB' => ['mariadb'],
        'PostgreSQL' => ['postgresql', 'postgres'],
        'MongoDB' => ['mongodb'],
        'Redis' => ['redis'],
        'Elasticsearch' => ['elasticsearch', 'elastic search'],
        'RabbitMQ' => ['rabbitmq'],
        'Kafka' => ['apache kafka', 'kafka'],
        'Docker' => ['docker'],
        'Kubernetes' => ['kubernetes', 'k8s'],
        'GCP' => ['google cloud platform', 'gcp'],
        'AWS' => ['amazon web services', 'aws'],
        'Azure' => ['microsoft azure', 'azure'],
        'GitLab CI/CD' => ['gitlab ci/cd', 'gitlab ci'],
        'Jenkins' => ['jenkins'],
        'SonarQube' => ['sonarqube'],
        'PHPUnit' => ['phpunit'],
        'PHPStan' => ['phpstan'],
        'Tailwind CSS' => ['tailwind css', 'tailwind'],
        'Vuetify' => ['vuetify'],
        'GraphQL' => ['graphql'],
        'REST API' => ['rest api', 'api rest', 'restful'],
        'DDD' => ['domain-driven design', 'domain driven design', 'ddd'],
        'CQRS' => ['cqrs'],
        'Hexagonal Architecture' => ['hexagonal architecture', 'architecture hexagonale'],
        'Java' => ['java'],
        'Spring Boot' => ['spring boot', 'spring framework', 'spring'],
        'Python' => ['python'],
        'Django' => ['django'],
        'FastAPI' => ['fastapi'],
        'Flask' => ['flask'],
        '.NET / C#' => ['asp.net', 'dotnet', '.net', 'c#'],
        'Node.js' => ['node.js', 'nodejs'],
        'NestJS' => ['nestjs', 'nest.js'],
        'Express.js' => ['express.js', 'expressjs'],
        'Go' => ['golang', 'go developer', 'go engineer', 'développeur go', 'developpeur go'],
        'Ruby' => ['ruby'],
        'Ruby on Rails' => ['ruby on rails', 'rails'],
        'Rust' => ['rust'],
        'Kotlin' => ['kotlin'],
        'Scala' => ['scala'],
        'C++' => ['c++', 'cpp'],
    ];

    /**
     * @return array{
     *   source: 'AI_REUSED'|'DETERMINISTIC',
     *   aiDecision: ?string,
     *   aiConfidence: ?int,
     *   technologies: list<string>,
     *   primaryTechnologies: list<string>,
     *   secondaryTechnologies: list<string>,
     *   matchingTechnologies: list<string>,
     *   missingTechnologies: list<string>,
     *   missingMustHaves: list<string>,
     *   missingNiceToHaves: list<string>
     * }
     */
    public function compare(JobOffer $job, UserSettings $settings): array
    {
        $jobText = $job->getTitle()."\n".$job->getDescription();
        $profileText = implode("\n", [...$settings->getTargetJobs(), ...$settings->getSkills()]);
        $jobTechnologies = $this->detectTechnologies($jobText, $settings->getSkills());
        $candidateTechnologies = $this->detectTechnologies($profileText, $settings->getSkills(), true);

        $jobArray = $job->toArray();
        $scoreReasons = is_array($jobArray['scoreReasons'] ?? null) ? $jobArray['scoreReasons'] : [];
        $ai = $this->extractAiMetadata($scoreReasons);

        $primaryTechnologies = $ai['primaryStackText'] !== null
            ? $this->detectTechnologies($ai['primaryStackText'], $settings->getSkills())
            : $this->detectTechnologies($job->getTitle(), $settings->getSkills());
        $primaryTechnologies = $this->intersection($primaryTechnologies, $jobTechnologies);
        $secondaryTechnologies = $this->difference($jobTechnologies, $primaryTechnologies);

        $matchingTechnologies = $this->intersection($jobTechnologies, $candidateTechnologies);
        $missingTechnologies = $this->difference($jobTechnologies, $candidateTechnologies);

        if ($ai['missingMustHavesText'] !== null) {
            $missingMustHaves = $this->intersection(
                $missingTechnologies,
                $this->detectTechnologies($ai['missingMustHavesText'], []),
            );
        } else {
            // Without reusable AI metadata, technologies explicitly present in the title
            // are treated as the strongest deterministic requirements. Everything else
            // remains a softer gap instead of being labelled mandatory without evidence.
            $missingMustHaves = $this->intersection(
                $missingTechnologies,
                $this->detectTechnologies($job->getTitle(), $settings->getSkills()),
            );
        }

        return [
            'source' => $ai['reused'] ? 'AI_REUSED' : 'DETERMINISTIC',
            'aiDecision' => $ai['decision'],
            'aiConfidence' => $ai['confidence'],
            'technologies' => $jobTechnologies,
            'primaryTechnologies' => $primaryTechnologies,
            'secondaryTechnologies' => $secondaryTechnologies,
            'matchingTechnologies' => $matchingTechnologies,
            'missingTechnologies' => $missingTechnologies,
            'missingMustHaves' => $missingMustHaves,
            'missingNiceToHaves' => $this->difference($missingTechnologies, $missingMustHaves),
        ];
    }

    /**
     * @param list<string> $extraTerms
     * @return list<string>
     */
    private function detectTechnologies(string $text, array $extraTerms = [], bool $includeAllExtraTerms = false): array
    {
        $result = [];
        foreach (self::TECHNOLOGY_ALIASES as $technology => $aliases) {
            foreach ($aliases as $alias) {
                if ($this->containsTerm($text, $alias)) {
                    $result[] = $technology;
                    break;
                }
            }
        }

        foreach ($extraTerms as $term) {
            $term = trim($term);
            if ($term === '' || mb_strlen($term) < 2) {
                continue;
            }
            if (!$includeAllExtraTerms && !$this->containsTerm($text, $term)) {
                continue;
            }
            if (!$this->containsInsensitive($result, $term)) {
                $result[] = $term;
            }
        }

        return array_values(array_unique($result));
    }

    private function containsTerm(string $text, string $term): bool
    {
        $term = trim($term);
        if ($term === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu';

        return preg_match($pattern, $text) === 1;
    }

    /** @param list<string> $values */
    private function containsInsensitive(array $values, string $needle): bool
    {
        $needle = mb_strtolower(trim($needle));
        foreach ($values as $value) {
            if (mb_strtolower(trim($value)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private function intersection(array $left, array $right): array
    {
        return array_values(array_filter(
            $left,
            fn (string $value): bool => $this->containsInsensitive($right, $value),
        ));
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private function difference(array $left, array $right): array
    {
        return array_values(array_filter(
            $left,
            fn (string $value): bool => !$this->containsInsensitive($right, $value),
        ));
    }

    /**
     * @param list<mixed> $scoreReasons
     * @return array{reused: bool, decision: ?string, confidence: ?int, primaryStackText: ?string, missingMustHavesText: ?string}
     */
    private function extractAiMetadata(array $scoreReasons): array
    {
        $decision = null;
        $confidence = null;
        $primaryStackText = null;
        $missingMustHavesText = null;
        $reused = false;

        foreach ($scoreReasons as $reason) {
            if (!is_string($reason)) {
                continue;
            }

            if (preg_match('/^Analyse IA\s*:\s*(MATCH|REVIEW|NO_MATCH)\s*[·-]\s*confiance\s+(\d{1,3})%/iu', $reason, $matches) === 1) {
                $decision = strtoupper($matches[1]);
                $confidence = max(0, min(100, (int) $matches[2]));
                $reused = true;
                continue;
            }

            $primaryPrefix = 'Stack principale détectée par IA : ';
            if (str_starts_with($reason, $primaryPrefix)) {
                $primaryStackText = trim(substr($reason, strlen($primaryPrefix)));
                $reused = true;
                continue;
            }

            $missingPrefix = 'Prérequis principaux manquants : ';
            if (str_starts_with($reason, $missingPrefix)) {
                $missingMustHavesText = trim(substr($reason, strlen($missingPrefix)));
                $reused = true;
            }
        }

        return [
            'reused' => $reused,
            'decision' => $decision,
            'confidence' => $confidence,
            'primaryStackText' => $primaryStackText,
            'missingMustHavesText' => $missingMustHavesText,
        ];
    }
}

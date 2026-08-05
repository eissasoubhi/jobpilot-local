<?php

declare(strict_types=1);

namespace App\JobCatalog\Application;

use App\Entity\JobOffer;
use App\Entity\JobSourceOccurrence;
use Doctrine\ORM\EntityManagerInterface;

final class CanonicalJobMatcher
{
    private const TITLE_STOP_WORDS = [
        'senior', 'junior', 'confirme', 'confirmee', 'h-f', 'f-h', 'h', 'f',
        'developpeur', 'developpeuse', 'developer', 'engineer', 'ingenieur',
        'consultant', 'consultante', 'full', 'stack', 'backend', 'frontend',
        'poste', 'mission', 'emploi', 'job', 'recherche', 'urgent', 'paris',
    ];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{job: JobOffer, matchType: string, score: int, reasons: list<string>}|null
     */
    public function find(array $payload): ?array
    {
        $normalizedUrl = $this->normalizeUrl((string) ($payload['sourceUrl'] ?? ''));
        if ($normalizedUrl !== null) {
            $occurrence = $this->em->getRepository(JobSourceOccurrence::class)->findOneBy([
                'normalizedUrl' => $normalizedUrl,
            ]);
            if ($occurrence instanceof JobSourceOccurrence) {
                return [
                    'job' => $occurrence->getJobOffer(),
                    'matchType' => 'EXACT_URL',
                    'score' => 100,
                    'reasons' => ['Même URL canonique déjà connue.'],
                ];
            }
        }

        $incomingTitle = $this->normalizeText((string) ($payload['title'] ?? ''));
        $incomingCompany = $this->normalizeText((string) ($payload['company'] ?? ''));
        if ($incomingTitle === '' || $incomingCompany === '') {
            return null;
        }

        $incomingTitleTokens = $this->significantTokens($incomingTitle);
        if ($incomingTitleTokens === []) {
            return null;
        }

        $best = null;
        $candidates = $this->em->getRepository(JobOffer::class)->findBy([], ['discoveredAt' => 'DESC'], 500);
        foreach ($candidates as $candidate) {
            if (!$candidate instanceof JobOffer) {
                continue;
            }

            $candidateTitle = $this->normalizeText($candidate->getTitle());
            $candidateCompany = $this->normalizeText($candidate->getCompany());
            if ($candidateTitle === '' || $candidateCompany === '') {
                continue;
            }

            $contractScore = $this->compatibility(
                (string) ($payload['contractType'] ?? ''),
                $candidate->getContractType(),
            );
            $locationScore = $this->compatibility(
                (string) ($payload['location'] ?? ''),
                $candidate->getLocation(),
            );
            $dateScore = $this->dateCompatibility(
                $payload['publishedAt'] ?? null,
                $candidate->getPublishedAt(),
            );

            if (
                $candidateTitle === $incomingTitle
                && $candidateCompany === $incomingCompany
                && $contractScore > 0.0
                && $locationScore > 0.0
                && $dateScore > 0.0
            ) {
                $reasons = ['Même intitulé normalisé.', 'Même entreprise normalisée.'];
                if ($contractScore === 1.0) {
                    $reasons[] = 'Même type de contrat.';
                }
                if ($locationScore === 1.0) {
                    $reasons[] = 'Même localisation.';
                }
                if ($dateScore === 1.0) {
                    $reasons[] = 'Dates de publication proches.';
                }

                return [
                    'job' => $candidate,
                    'matchType' => 'EXACT_FINGERPRINT',
                    'score' => 98,
                    'reasons' => $reasons,
                ];
            }

            $titleSimilarity = $this->jaccard(
                $incomingTitleTokens,
                $this->significantTokens($candidateTitle),
            );
            $companySimilarity = $this->textSimilarity($incomingCompany, $candidateCompany);
            if ($titleSimilarity < 0.72 || $companySimilarity < 0.82) {
                continue;
            }

            if ($contractScore === 0.0 || $locationScore === 0.0 || $dateScore === 0.0) {
                continue;
            }

            $score = (int) round(
                ($titleSimilarity * 62)
                + ($companySimilarity * 26)
                + ($contractScore * 5)
                + ($locationScore * 4)
                + ($dateScore * 3),
            );
            if ($score < 84 || ($best !== null && $score <= $best['score'])) {
                continue;
            }

            $reasons = [
                sprintf('Intitulé similaire à %d %%', (int) round($titleSimilarity * 100)),
                sprintf('Entreprise similaire à %d %%', (int) round($companySimilarity * 100)),
            ];
            if ($contractScore === 1.0) {
                $reasons[] = 'Même type de contrat.';
            }
            if ($locationScore === 1.0) {
                $reasons[] = 'Même localisation.';
            }
            if ($dateScore === 1.0) {
                $reasons[] = 'Dates de publication proches.';
            }

            $best = [
                'job' => $candidate,
                'matchType' => 'SIMILARITY',
                'score' => $score,
                'reasons' => $reasons,
            ];
        }

        return $best;
    }

    public function normalizeUrl(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '' || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = preg_replace('~/+~', '/', (string) ($parts['path'] ?? '/')) ?? '/';
        $path = $path === '/' ? '/' : rtrim($path, '/');
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/^(utm_|trk|tracking|ref|referrer|source|campaign|mc_|gclid|fbclid)/i', (string) $key) === 1) {
                unset($query[$key]);
            }
        }
        ksort($query);

        return 'https://'.$host.$path.($query !== [] ? '?'.http_build_query($query) : '');
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
            if (is_string($transliterated)) {
                $value = $transliterated;
            }
        } else {
            $value = mb_strtolower(strtr($value, [
                'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
                'ù' => 'u', 'û' => 'u', 'ü' => 'u', '’' => "'",
            ]));
        }

        $value = preg_replace('/\b(?:sarl|sas|sa|inc|ltd|gmbh)\b/u', ' ', mb_strtolower($value)) ?? $value;
        $value = preg_replace('/[^a-z0-9+#.]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** @return list<string> */
    private function significantTokens(string $value): array
    {
        $tokens = preg_split('/\s+/', $value) ?: [];
        $tokens = array_filter($tokens, static fn (string $token): bool =>
            mb_strlen($token) >= 2 && !in_array($token, self::TITLE_STOP_WORDS, true)
        );

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /** @param list<string> $left @param list<string> $right */
    private function jaccard(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique([...$left, ...$right]));

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    private function textSimilarity(string $left, string $right): float
    {
        if ($left === $right) {
            return 1.0;
        }
        if ($left === '' || $right === '') {
            return 0.0;
        }

        $maxLength = max(strlen($left), strlen($right));
        if ($maxLength === 0) {
            return 1.0;
        }

        return max(0.0, 1 - (levenshtein($left, $right) / $maxLength));
    }

    private function compatibility(string $left, string $right): float
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);
        if ($left === '' || $right === '') {
            return 0.5;
        }
        if ($left === $right || str_contains($left, $right) || str_contains($right, $left)) {
            return 1.0;
        }

        return 0.0;
    }

    private function dateCompatibility(mixed $incoming, ?\DateTimeImmutable $candidate): float
    {
        if ($incoming === null || $incoming === '' || $candidate === null) {
            return 0.5;
        }

        try {
            $incomingDate = new \DateTimeImmutable((string) $incoming);
        } catch (\Throwable) {
            return 0.5;
        }

        $days = abs($incomingDate->getTimestamp() - $candidate->getTimestamp()) / 86400;

        return match (true) {
            $days <= 7 => 1.0,
            $days <= 30 => 0.5,
            default => 0.0,
        };
    }
}

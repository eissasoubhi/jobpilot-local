<?php

declare(strict_types=1);

namespace App\Messaging\Application;

final class AssistedJobPlatformCatalog
{
    /**
     * @return list<array{code: string, name: string, hostPattern: string, pathPatterns: list<string>, aliases: list<string>}>
     */
    public function all(): array
    {
        return [
            [
                'code' => 'linkedin',
                'name' => 'LinkedIn',
                'hostPattern' => '/(^|\.)linkedin\.com$/i',
                'pathPatterns' => ['~/jobs/view/.+~i', '~/comm/jobs/view/.+~i'],
                'aliases' => ['linkedin'],
            ],
            [
                'code' => 'indeed',
                'name' => 'Indeed',
                'hostPattern' => '/(^|\.)indeed\.[a-z.]+$/i',
                'pathPatterns' => ['~/viewjob(?:/|$)~i', '~/rc/clk(?:/|$)~i'],
                'aliases' => ['indeed'],
            ],
            [
                'code' => 'apec',
                'name' => 'APEC',
                'hostPattern' => '/(^|\.)apec\.fr$/i',
                'pathPatterns' => ['~/emploi/detail-offre/.+~i', '~/detail-offre/.+~i'],
                'aliases' => ['apec'],
            ],
            [
                'code' => 'hellowork',
                'name' => 'Hellowork',
                'hostPattern' => '/(^|\.)hellowork\.com$/i',
                'pathPatterns' => ['~/emplois/.+~i'],
                'aliases' => ['hellowork', 'hello work'],
            ],
            [
                'code' => 'welcome-to-the-jungle',
                'name' => 'Welcome to the Jungle',
                'hostPattern' => '/(^|\.)welcometothejungle\.com$/i',
                'pathPatterns' => ['~/companies/[^/]+/jobs/.+~i'],
                'aliases' => ['welcome to the jungle', 'welcometothejungle'],
            ],
            [
                'code' => 'free-work',
                'name' => 'Free-Work',
                'hostPattern' => '/(^|\.)free-work\.com$/i',
                'pathPatterns' => ['~/tech-it/job-mission/[^/]+/[^/]+~i'],
                'aliases' => ['free-work', 'free work'],
            ],
            [
                'code' => 'lesjeudis',
                'name' => 'LesJeudis',
                'hostPattern' => '/(^|\.)lesjeudis\.com$/i',
                'pathPatterns' => ['~/job/[^/]+~i'],
                'aliases' => ['lesjeudis', 'les jeudis'],
            ],
            [
                'code' => 'lehibou',
                'name' => 'Le Hibou',
                'hostPattern' => '/(^|\.)lehibou\.com$/i',
                'pathPatterns' => ['~/missions?/.+~i'],
                'aliases' => ['le hibou', 'lehibou'],
            ],
            [
                'code' => 'france-travail',
                'name' => 'France Travail',
                'hostPattern' => '/(^|\.)francetravail\.fr$/i',
                'pathPatterns' => ['~/offres/recherche/detail/.+~i'],
                'aliases' => ['france travail', 'francetravail'],
            ],
        ];
    }

    /** @return array{code: string, name: string}|null */
    public function forUrl(string $url): ?array
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($host === '') {
            return null;
        }

        foreach ($this->all() as $platform) {
            if (preg_match($platform['hostPattern'], $host) !== 1) {
                continue;
            }
            foreach ($platform['pathPatterns'] as $pattern) {
                if (preg_match($pattern, $path) === 1) {
                    return ['code' => $platform['code'], 'name' => $platform['name']];
                }
            }
        }

        return null;
    }

    /** @return array{code: string, name: string}|null */
    public function forText(string $text): ?array
    {
        $value = $this->normalize($text);
        if ($value === '') {
            return null;
        }

        foreach ($this->all() as $platform) {
            foreach ($platform['aliases'] as $alias) {
                if (str_contains($value, $this->normalize($alias))) {
                    return ['code' => $platform['code'], 'name' => $platform['name']];
                }
            }
        }

        return null;
    }

    public function supportsCode(string $code): bool
    {
        $code = mb_strtolower(trim($code));
        foreach ($this->all() as $platform) {
            if ($platform['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', '’' => "'",
        ]);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}

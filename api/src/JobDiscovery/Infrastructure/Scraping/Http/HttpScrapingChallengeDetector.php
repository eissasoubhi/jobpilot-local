<?php

declare(strict_types=1);

namespace App\JobDiscovery\Infrastructure\Scraping\Http;

final class HttpScrapingChallengeDetector
{
    /**
     * Detects strong challenge/captcha signals without attempting to bypass them.
     *
     * @param array<string, list<string>> $headers
     */
    public function detect(string $body, array $headers = []): ?string
    {
        if ($this->headerContains($headers, 'cf-mitigated', 'challenge')) {
            return 'Cloudflare challenge';
        }

        $normalized = strtolower($body);
        if ($normalized === '') {
            return null;
        }

        $strongSignals = [
            '/cdn-cgi/challenge-platform/' => 'Cloudflare challenge',
            'challenges.cloudflare.com' => 'Cloudflare challenge',
            'cf-chl-' => 'Cloudflare challenge',
            'geo.captcha-delivery.com' => 'DataDome challenge',
            'captcha-delivery.com/captcha/' => 'DataDome challenge',
            'datadome-captcha' => 'DataDome challenge',
            'captcha.px-cdn.net' => 'PerimeterX challenge',
        ];

        foreach ($strongSignals as $signal => $label) {
            if (str_contains($normalized, $signal)) {
                return $label;
            }
        }

        $humanVerification = str_contains($normalized, 'verify you are human')
            || str_contains($normalized, 'verify that you are human')
            || str_contains($normalized, 'vérifiez que vous êtes humain')
            || str_contains($normalized, 'confirmez que vous êtes humain');
        if (!$humanVerification) {
            return null;
        }

        if (str_contains($normalized, 'g-recaptcha')
            || str_contains($normalized, 'www.google.com/recaptcha/')
            || str_contains($normalized, 'www.recaptcha.net/recaptcha/')) {
            return 'Google reCAPTCHA';
        }

        if (str_contains($normalized, 'h-captcha') || str_contains($normalized, 'hcaptcha.com/1/api.js')) {
            return 'hCaptcha';
        }

        $challengeContext = str_contains($normalized, 'captcha')
            || str_contains($normalized, 'challenge')
            || str_contains($normalized, 'bot detection')
            || str_contains($normalized, 'anti-bot');

        return $challengeContext ? 'Human verification challenge' : null;
    }

    /** @param array<string, list<string>> $headers */
    private function headerContains(array $headers, string $name, string $needle): bool
    {
        $values = $headers[strtolower($name)] ?? [];
        foreach ($values as $value) {
            if (str_contains(strtolower((string) $value), strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;
use App\Messaging\Application\AssistedJobPlatformCatalog;

final class CustomScraperPresetCatalog
{
    public const STATUS_AUTHORIZATION_REQUIRED = 'AUTHORIZATION_REQUIRED';
    public const STATUS_ASSISTED_ONLY = 'ASSISTED_ONLY';
    private const REVIEW_TTL_DAYS = 90;
    private const REVIEW_WARNING_DAYS = 14;

    private AssistedJobPlatformCatalog $assistedPlatforms;
    private \DateTimeImmutable $today;

    public function __construct(?AssistedJobPlatformCatalog $assistedPlatforms = null, ?string $today = null)
    {
        $this->assistedPlatforms = $assistedPlatforms ?? new AssistedJobPlatformCatalog();
        $this->today = new \DateTimeImmutable($today ?? 'today');
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return [
            $this->finalize([
                'slug' => 'apec-php-symfony',
                'platformCode' => 'apec',
                'name' => 'APEC — PHP / Symfony',
                'listingUrl' => 'https://www.apec.fr/candidat/recherche-emploi.html/emploi?motsCles=PHP%20Symfony',
                'mode' => CustomScraperSource::MODE_BROWSER,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation écrite à confirmer',
                'canPrefill' => true,
                'reason' => 'Une autorisation écrite de scraping a été signalée pour JobPilot le 11 août 2026. Le preset reste protégé par une confirmation locale. Le flux XML officiel Apec reste prioritaire lorsqu’un accès partenaire applicable est disponible.',
                'recommendedAction' => 'Consigner la référence de l’accord Apec, privilégier le flux XML officiel lorsqu’il est accessible, sinon tester cette recherche en mode Browser avant toute activation.',
                'termsUrl' => 'https://corporate.apec.fr/home/informations-legales-et-conditio.html',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 3,
                'maxDetails' => 15,
            ]),
            $this->finalize([
                'slug' => 'lehibou-symfony',
                'platformCode' => 'lehibou',
                'name' => 'LeHibou — Développeur Symfony',
                'listingUrl' => 'https://www.lehibou.com/freelance/digital-informatique-industrielle-embarque/developpeur-symfony',
                'mode' => CustomScraperSource::MODE_AUTO,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation écrite à confirmer',
                'canPrefill' => true,
                'reason' => 'Une autorisation écrite de scraping a été signalée pour JobPilot le 11 août 2026. Une référence locale et une confirmation explicite restent obligatoires avant de créer une source automatisée.',
                'recommendedAction' => 'Consigner la référence de l’accord LeHibou, ajouter la source désactivée, puis exécuter diagnostic et prévisualisation avant activation. Les liens reçus par Gmail restent également pris en charge.',
                'termsUrl' => 'https://www.lehibou.com/conditions-generales-utilisation',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 3,
                'maxDetails' => 15,
            ]),
            $this->finalize([
                'slug' => 'free-work-symfony',
                'platformCode' => 'free-work',
                'name' => 'Free-Work — Symfony',
                'listingUrl' => 'https://www.free-work.com/fr/tech-it/jobs/symfony',
                'mode' => CustomScraperSource::MODE_HTTP,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation écrite à confirmer',
                'canPrefill' => true,
                'reason' => 'Une autorisation écrite de scraping a été signalée pour JobPilot le 11 août 2026. Le preset reste protégé par une confirmation locale afin de vérifier que l’accord couvre bien les pages et l’usage ciblés avant toute activation.',
                'recommendedAction' => 'Consigner la référence de l’accord Free-Work, ajouter la source désactivée, puis exécuter le diagnostic et la prévisualisation avant activation. Gmail/import assisté restent disponibles.',
                'termsUrl' => 'https://www.free-work.com/fr/terms',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 3,
                'maxDetails' => 15,
            ]),
            $this->finalize([
                'slug' => 'welcome-to-the-jungle',
                'platformCode' => 'welcome-to-the-jungle',
                'name' => 'Welcome to the Jungle',
                'listingUrl' => 'https://www.welcometothejungle.com/fr/jobs?query=php%20symfony',
                'mode' => CustomScraperSource::MODE_BROWSER,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation écrite à confirmer',
                'canPrefill' => true,
                'reason' => 'Une autorisation écrite de scraping a été signalée pour JobPilot le 11 août 2026. JobPilot demande toujours une référence et une confirmation explicite avant de créer une source automatisée.',
                'recommendedAction' => 'Consigner la référence de l’accord Welcome to the Jungle, ajouter la source désactivée, puis diagnostiquer le mode Browser avant activation. Gmail/import assisté restent disponibles.',
                'termsUrl' => 'https://www.welcometothejungle.com/fr/pages/terms',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 1,
                'maxDetails' => 0,
            ]),
            $this->finalize([
                'slug' => 'hellowork-php',
                'platformCode' => 'hellowork',
                'name' => 'Hellowork — PHP',
                'listingUrl' => 'https://www.hellowork.com/fr-fr/emploi/mot-cle_php.html',
                'mode' => CustomScraperSource::MODE_HTTP,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation écrite à confirmer',
                'canPrefill' => true,
                'reason' => 'Une autorisation écrite de scraping a été signalée pour JobPilot le 11 août 2026. La création reste désactivée par défaut et requiert une confirmation locale de la portée de cet accord.',
                'recommendedAction' => 'Consigner la référence de l’accord Hellowork, ajouter la source désactivée, puis lancer diagnostic et prévisualisation avant activation. Gmail/import assisté restent disponibles.',
                'termsUrl' => 'https://recruteur.hellowork.com/cgv-abo-lib',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 1,
                'maxDetails' => 0,
            ]),
            $this->finalize([
                'slug' => 'lesjeudis',
                'platformCode' => 'lesjeudis',
                'name' => 'LesJeudis',
                'listingUrl' => 'https://lesjeudis.com/fr',
                'mode' => CustomScraperSource::MODE_HTTP,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation écrite à confirmer',
                'canPrefill' => true,
                'reason' => 'Une autorisation écrite de scraping a été signalée pour JobPilot le 11 août 2026. Une référence et une confirmation explicite restent obligatoires avant toute création de source automatisée.',
                'recommendedAction' => 'Consigner la référence de l’accord LesJeudis, ajouter la source désactivée, puis exécuter diagnostic et prévisualisation avant activation. Gmail/import assisté restent disponibles.',
                'termsUrl' => 'https://lesjeudis.com/fr/cgu',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 1,
                'maxDetails' => 0,
            ]),
        ];
    }

    /** @param array<string, mixed> $preset @return array<string, mixed> */
    private function finalize(array $preset): array
    {
        $platformCode = (string) ($preset['platformCode'] ?? '');
        $preset['gmailSupported'] = $this->assistedPlatforms->supportsCode($platformCode);
        $preset['gmailPlatformCode'] = $platformCode;

        $reviewedAt = new \DateTimeImmutable((string) ($preset['reviewedAt'] ?? '1970-01-01'));
        $reviewDueAt = $reviewedAt->modify('+'.self::REVIEW_TTL_DAYS.' days');
        $signedDaysRemaining = (int) $this->today->diff($reviewDueAt)->format('%r%a');
        $reviewFresh = $signedDaysRemaining >= 0;
        $reviewDaysRemaining = max(0, $signedDaysRemaining);
        $preset['reviewDueAt'] = $reviewDueAt->format('Y-m-d');
        $preset['reviewFresh'] = $reviewFresh;
        $preset['reviewTtlDays'] = self::REVIEW_TTL_DAYS;
        $preset['reviewDaysRemaining'] = $reviewDaysRemaining;
        $preset['reviewRenewalRecommended'] = $reviewFresh && $reviewDaysRemaining <= self::REVIEW_WARNING_DAYS;
        $preset['canPrefill'] = ($preset['canPrefill'] ?? false) === true && $reviewFresh;

        return $preset;
    }
}

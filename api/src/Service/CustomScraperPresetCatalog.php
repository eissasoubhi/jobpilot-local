<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomScraperSource;

final class CustomScraperPresetCatalog
{
    public const STATUS_AUTHORIZATION_REQUIRED = 'AUTHORIZATION_REQUIRED';
    public const STATUS_ASSISTED_ONLY = 'ASSISTED_ONLY';

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return [
            [
                'slug' => 'apec-php-symfony',
                'name' => 'APEC — PHP / Symfony',
                'listingUrl' => 'https://www.apec.fr/candidat/recherche-emploi.html/emploi?motsCles=PHP%20Symfony',
                'mode' => CustomScraperSource::MODE_BROWSER,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation explicite requise',
                'canPrefill' => true,
                'reason' => 'Le domaine publie des signaux d’indexation publics, mais les informations légales Apec réservent la reproduction et l’exploitation sans accord préalable. Une autorisation explicite doit être obtenue avant activation.',
                'recommendedAction' => 'Obtenir/consigner l’autorisation Apec, puis tester cette recherche en mode Browser.',
                'termsUrl' => 'https://corporate.apec.fr/home/informations-legales-et-conditio.html',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 3,
                'maxDetails' => 15,
            ],
            [
                'slug' => 'lehibou-symfony',
                'name' => 'LeHibou — Développeur Symfony',
                'listingUrl' => 'https://www.lehibou.com/freelance/digital-informatique-industrielle-embarque/developpeur-symfony',
                'mode' => CustomScraperSource::MODE_AUTO,
                'complianceStatus' => self::STATUS_AUTHORIZATION_REQUIRED,
                'complianceLabel' => 'Autorisation explicite requise',
                'canPrefill' => true,
                'reason' => 'La page Symfony est publique, mais les CGU réservent l’exploitation, la reproduction et la réutilisation des éléments du site sans accord préalable écrit.',
                'recommendedAction' => 'Obtenir/consigner un accord écrit LeHibou avant d’activer la collecte automatique.',
                'termsUrl' => 'https://www.lehibou.com/conditions-generales-utilisation',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 3,
                'maxDetails' => 15,
            ],
            [
                'slug' => 'free-work-symfony',
                'name' => 'Free-Work — Symfony',
                'listingUrl' => 'https://www.free-work.com/fr/tech-it/jobs/symfony',
                'mode' => CustomScraperSource::MODE_HTTP,
                'complianceStatus' => self::STATUS_ASSISTED_ONLY,
                'complianceLabel' => 'Import assisté uniquement',
                'canPrefill' => false,
                'reason' => 'Les CGU limitent l’usage aux besoins personnels du service et interdisent notamment l’extraction d’une part substantielle de la base de données.',
                'recommendedAction' => 'Utiliser une alerte e-mail, une extension/import manuel ou obtenir un accord explicite/API avant toute collecte automatisée.',
                'termsUrl' => 'https://www.free-work.com/fr/terms',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 3,
                'maxDetails' => 15,
            ],
            [
                'slug' => 'welcome-to-the-jungle',
                'name' => 'Welcome to the Jungle',
                'listingUrl' => 'https://www.welcometothejungle.com/fr/jobs?query=php%20symfony',
                'mode' => CustomScraperSource::MODE_BROWSER,
                'complianceStatus' => self::STATUS_ASSISTED_ONLY,
                'complianceLabel' => 'Import assisté uniquement',
                'canPrefill' => false,
                'reason' => 'Les CGU 2026 interdisent explicitement l’utilisation de robots, scripts ou autres procédés pour l’extraction automatisée de données (scraping).',
                'recommendedAction' => 'Passer par Gmail, une extension/import utilisateur ou une API/autorisation partenaire.',
                'termsUrl' => 'https://www.welcometothejungle.com/fr/pages/terms',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 1,
                'maxDetails' => 0,
            ],
            [
                'slug' => 'hellowork-php',
                'name' => 'Hellowork — PHP',
                'listingUrl' => 'https://www.hellowork.com/fr-fr/emploi/mot-cle_php.html',
                'mode' => CustomScraperSource::MODE_HTTP,
                'complianceStatus' => self::STATUS_ASSISTED_ONLY,
                'complianceLabel' => 'Import assisté uniquement',
                'canPrefill' => false,
                'reason' => 'Les conditions publiques Hellowork consultées interdisent d’extraire le contenu du site, notamment par scraping. Les pages et robots.txt peuvent en outre appliquer des restrictions distinctes selon les routes.',
                'recommendedAction' => 'Utiliser les alertes e-mail/import assisté ou une source explicitement autorisée par Hellowork.',
                'termsUrl' => 'https://recruteur.hellowork.com/cgv-abo-lib',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 1,
                'maxDetails' => 0,
            ],
            [
                'slug' => 'lesjeudis',
                'name' => 'LesJeudis',
                'listingUrl' => 'https://lesjeudis.com/fr',
                'mode' => CustomScraperSource::MODE_HTTP,
                'complianceStatus' => self::STATUS_ASSISTED_ONLY,
                'complianceLabel' => 'Import assisté uniquement',
                'canPrefill' => false,
                'reason' => 'Les CGU du 20 janvier 2026 interdisent explicitement le recours à un logiciel robot ou à tout autre procédé automatisé de scraping.',
                'recommendedAction' => 'Utiliser une alerte e-mail, un import utilisateur ou un accord/API explicite.',
                'termsUrl' => 'https://lesjeudis.com/fr/cgu',
                'reviewedAt' => '2026-08-10',
                'syncIntervalMinutes' => 360,
                'maxPages' => 1,
                'maxDetails' => 0,
            ],
        ];
    }
}

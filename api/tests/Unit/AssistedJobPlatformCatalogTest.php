<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Messaging\Application\AssistedJobPlatformCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssistedJobPlatformCatalogTest extends TestCase
{
    #[DataProvider('jobUrls')]
    public function testRecognizesSupportedAlertJobUrls(string $url, string $code, string $name): void
    {
        $platform = (new AssistedJobPlatformCatalog())->forUrl($url);

        self::assertSame(['code' => $code, 'name' => $name], $platform);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function jobUrls(): iterable
    {
        yield 'linkedin' => ['https://www.linkedin.com/jobs/view/123456789', 'linkedin', 'LinkedIn'];
        yield 'indeed' => ['https://fr.indeed.com/viewjob?jk=abc', 'indeed', 'Indeed'];
        yield 'apec' => ['https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/123', 'apec', 'APEC'];
        yield 'hellowork' => ['https://www.hellowork.com/fr-fr/emplois/123456.html', 'hellowork', 'Hellowork'];
        yield 'welcome to the jungle' => ['https://www.welcometothejungle.com/fr/companies/acme/jobs/developpeur-symfony_paris', 'welcome-to-the-jungle', 'Welcome to the Jungle'];
        yield 'free-work jobs' => ['https://www.free-work.com/fr/tech-it/jobs/developpeur-symfony/paris', 'free-work', 'Free-Work'];
        yield 'free-work missions' => ['https://www.free-work.com/fr/tech-it/missions/lead-developer-php', 'free-work', 'Free-Work'];
        yield 'lesjeudis singular job' => ['https://lesjeudis.com/fr/job/developpeur-php-symfony', 'lesjeudis', 'LesJeudis'];
        yield 'lehibou mission' => ['https://www.lehibou.com/mission/developpeur-symfony', 'lehibou', 'Le Hibou'];
        yield 'lehibou public freelance page' => ['https://www.lehibou.com/freelance/digital-informatique-industrielle-embarque/developpeur-symfony', 'lehibou', 'Le Hibou'];
        yield 'france travail' => ['https://candidat.francetravail.fr/offres/recherche/detail/123ABC', 'france-travail', 'France Travail'];
    }

    public function testRejectsNonJobAndLookalikeDomains(): void
    {
        $catalog = new AssistedJobPlatformCatalog();

        self::assertNull($catalog->forUrl('https://www.welcometothejungle.com/fr/pages/terms'));
        self::assertNull($catalog->forUrl('https://fake-linkedin.com/jobs/view/123'));
        self::assertNull($catalog->forUrl('https://www.free-work.com/fr/terms'));
    }

    public function testTextRecognitionUsesTheSameStablePlatformNames(): void
    {
        $catalog = new AssistedJobPlatformCatalog();

        self::assertSame(['code' => 'free-work', 'name' => 'Free-Work'], $catalog->forText('Votre alerte Free Work du jour'));
        self::assertSame(['code' => 'welcome-to-the-jungle', 'name' => 'Welcome to the Jungle'], $catalog->forText('Welcome to the Jungle - nouvelles offres'));
        self::assertSame(['code' => 'lehibou', 'name' => 'Le Hibou'], $catalog->forText('LeHibou vous propose une mission'));
    }
}

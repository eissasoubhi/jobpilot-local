<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Scraping\Html\GenericPaginationDetector;
use PHPUnit\Framework\TestCase;

final class GenericPaginationDetectorTest extends TestCase
{
    public function testPrefersExplicitRelNextOnSameDomain(): void
    {
        $result = $this->detector()->detect(
            '<html><body><a rel="next" href="?page=2">Suivant</a></body></html>',
            'https://jobs.example.test/offres?page=1',
        );

        self::assertSame('https://jobs.example.test/offres?page=2', $result['nextUrl']);
        self::assertSame('REL_NEXT', $result['strategy']);
        self::assertSame('HIGH', $result['confidence']);
    }

    public function testDetectsFrenchNextLabel(): void
    {
        $result = $this->detector()->detect(
            '<html><body><nav aria-label="Pagination"><a href="/offres/page/2">Page suivante</a></nav></body></html>',
            'https://jobs.example.test/offres/page/1',
        );

        self::assertSame('https://jobs.example.test/offres/page/2', $result['nextUrl']);
        self::assertSame('NEXT_LABEL', $result['strategy']);
        self::assertSame('MEDIUM', $result['confidence']);
    }

    public function testAllowsArrowOnlyInsidePaginationNavigation(): void
    {
        $result = $this->detector()->detect(
            '<html><body><nav class="pagination"><a href="../page/3">›</a></nav></body></html>',
            'https://jobs.example.test/offres/page/2',
        );

        self::assertSame('https://jobs.example.test/offres/page/3', $result['nextUrl']);
    }

    public function testRejectsOffDomainNextLinks(): void
    {
        $result = $this->detector()->detect(
            '<html><body><a rel="next" href="https://other.example.net/jobs?page=2">Next</a></body></html>',
            'https://jobs.example.test/jobs?page=1',
        );

        self::assertNull($result['nextUrl']);
        self::assertNull($result['strategy']);
    }

    public function testRejectsSamePageAndUnrelatedArrowLinks(): void
    {
        $html = <<<'HTML'
<html><body>
<a rel="next" href="https://jobs.example.test/jobs?page=1">Next</a>
<a href="/company">›</a>
</body></html>
HTML;
        $result = $this->detector()->detect($html, 'https://jobs.example.test/jobs?page=1');

        self::assertNull($result['nextUrl']);
    }

    private function detector(): GenericPaginationDetector
    {
        return new GenericPaginationDetector();
    }
}

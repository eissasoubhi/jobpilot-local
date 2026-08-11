<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JobDiscovery\Infrastructure\Scraping\Http\HttpScrapingChallengeDetector;
use PHPUnit\Framework\TestCase;

final class HttpScrapingChallengeDetectorTest extends TestCase
{
    private HttpScrapingChallengeDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new HttpScrapingChallengeDetector();
    }

    public function testDetectsCloudflareChallengeHeader(): void
    {
        self::assertSame(
            'Cloudflare challenge',
            $this->detector->detect('<html>Temporary page</html>', ['cf-mitigated' => ['challenge']]),
        );
    }

    public function testDetectsStrongChallengeBodyMarkers(): void
    {
        self::assertSame(
            'Cloudflare challenge',
            $this->detector->detect('<script src="/cdn-cgi/challenge-platform/h/g/orchestrate/chl_page/v1"></script>'),
        );
        self::assertSame(
            'DataDome challenge',
            $this->detector->detect('<script src="https://geo.captcha-delivery.com/captcha/?initialCid=x"></script>'),
        );
    }

    public function testCaptchaWidgetNeedsHumanVerificationContext(): void
    {
        self::assertNull($this->detector->detect('<form><div class="g-recaptcha" data-sitekey="contact-form"></div></form>'));
        self::assertSame(
            'Google reCAPTCHA',
            $this->detector->detect('<h1>Verify you are human</h1><div class="g-recaptcha" data-sitekey="challenge"></div>'),
        );
        self::assertSame(
            'hCaptcha',
            $this->detector->detect('<h1>Verify that you are human</h1><div class="h-captcha"></div>'),
        );
    }

    public function testHumanVerificationPhraseRequiresChallengeContext(): void
    {
        self::assertNull($this->detector->detect('<p>Please verify you are human before contacting support.</p>'));
        self::assertSame(
            'Human verification challenge',
            $this->detector->detect('<h1>Verify you are human</h1><form id="captcha-challenge"></form>'),
        );
    }

    public function testNormalJobPageIsNotClassifiedAsChallenge(): void
    {
        $body = '<html><head><title>Développeur Symfony</title></head><body>'
            .'<p>Rejoignez notre équipe pour relever un challenge technique.</p>'
            .'<form><div class="g-recaptcha" data-sitekey="contact-form"></div></form>'
            .'</body></html>';

        self::assertNull($this->detector->detect($body, ['content-type' => ['text/html']]));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\JobCatalog\Application\ProfileFilteredJobOffer;
use PHPUnit\Framework\TestCase;

final class ProfileFilteredJobOfferTest extends TestCase
{
    public function testKeepsOnlyWhitelistedAggregateReasonCodes(): void
    {
        $exception = new ProfileFilteredJobOffer(35, 0.94, [
            ProfileFilteredJobOffer::REASON_SCORE_BELOW_THRESHOLD,
            ProfileFilteredJobOffer::REASON_MISSING_MUST_HAVE,
            ProfileFilteredJobOffer::REASON_MISSING_MUST_HAVE,
            'Java / contenu sensible de l’offre',
            ProfileFilteredJobOffer::REASON_EXPLICIT_CONFLICT,
        ]);

        self::assertSame(35, $exception->score);
        self::assertSame(0.94, $exception->confidence);
        self::assertSame([
            ProfileFilteredJobOffer::REASON_SCORE_BELOW_THRESHOLD,
            ProfileFilteredJobOffer::REASON_MISSING_MUST_HAVE,
            ProfileFilteredJobOffer::REASON_EXPLICIT_CONFLICT,
        ], $exception->reasonCodes);
        self::assertNotContains('Java / contenu sensible de l’offre', $exception->reasonCodes);
    }
}

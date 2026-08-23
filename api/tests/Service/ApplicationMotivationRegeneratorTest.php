<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;
use App\Service\ApplicationMotivationRegenerator;
use App\Service\GroundedCoverLetterBuilder;
use PHPUnit\Framework\TestCase;

final class ApplicationMotivationRegeneratorTest extends TestCase
{
    public function testShortMessageNeverExceedsRequestedMaximum(): void
    {
        $profile = (new CandidateProfile())->fill([
            'fullName' => 'Test Candidate',
            'yearsOfExperience' => 11,
            'availability' => 'Immédiate',
        ]);
        $job = (new JobOffer())->fill([
            'title' => 'Développeur Cloud Backend Senior avec responsabilités techniques étendues',
            'company' => 'Groupe Example International',
            'language' => 'fr',
            'description' => 'Description de test suffisamment longue.',
        ]);
        $service = new ApplicationMotivationRegenerator(new GroundedCoverLetterBuilder());

        foreach ([400, 250, 120, 50] as $maximum) {
            $message = $service->message($job, $profile, $maximum);

            self::assertNotSame('', trim($message));
            self::assertLessThanOrEqual($maximum, mb_strlen($message), sprintf(
                'Le message généré doit respecter strictement la limite de %d caractères.',
                $maximum,
            ));
        }
    }
}

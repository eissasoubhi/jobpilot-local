<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Entity\JobOffer;
use App\Entity\UserSettings;

interface AiJobMatchAnalyzerInterface
{
    public function analyze(JobOffer $job, UserSettings $settings): ?AiJobMatchAnalysis;
}

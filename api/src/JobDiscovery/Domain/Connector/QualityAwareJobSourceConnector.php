<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface QualityAwareJobSourceConnector extends JobSourceConnector
{
    public function qualityProfile(): ConnectorQualityProfile;
}

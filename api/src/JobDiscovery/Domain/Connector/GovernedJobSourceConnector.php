<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface GovernedJobSourceConnector extends JobSourceConnector
{
    public function policy(): ConnectorPolicy;
}

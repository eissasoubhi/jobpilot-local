<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

use App\JobDiscovery\Domain\Connector\JobSourceConnector;

interface DynamicJobSourceConnectorProvider
{
    /** @return iterable<JobSourceConnector> */
    public function connectors(): iterable;
}

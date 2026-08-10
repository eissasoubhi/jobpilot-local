<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface ScheduledJobSourceConnector extends JobSourceConnector
{
    public function syncIntervalSeconds(): int;
}

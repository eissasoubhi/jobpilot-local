<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface VersionedJobSourceConnector extends JobSourceConnector
{
    public function parserVersion(): string;
}

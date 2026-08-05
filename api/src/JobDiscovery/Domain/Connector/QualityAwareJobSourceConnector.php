<?php

declare(strict_types=1);

namespace App\JobDiscovery\Domain\Connector;

interface QualityAwareJobSourceConnector extends JobSourceConnector
{
    /**
     * @return array{
     *   required: list<string>,
     *   recommended: list<string>
     * }
     */
    public function qualityFields(): array;
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\JobDiscovery\Domain\Connector\JobSourceConnector;

/**
 * @deprecated Implement JobSourceConnector directly for new connectors.
 */
interface JobProviderInterface extends JobSourceConnector
{
}

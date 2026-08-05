<?php

declare(strict_types=1);

namespace App\JobDiscovery\Application;

use App\Entity\SourceConnector;
use Doctrine\ORM\EntityManagerInterface;

final class ConnectorFreshnessReportProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConnectorFreshnessAnalyzer $freshnessAnalyzer,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reports(int $intervalSeconds): array
    {
        $intervalSeconds = max(900, $intervalSeconds);
        $connectors = $this->entityManager->getRepository(SourceConnector::class)->findBy([], ['name' => 'ASC']);
        $reports = [];

        foreach ($connectors as $connector) {
            if (!$connector instanceof SourceConnector) {
                continue;
            }

            $reports[] = [
                'code' => $connector->getCode(),
                'name' => $connector->getName(),
                ...$this->freshnessAnalyzer->analyze(
                    $connector->getLastSyncedAt(),
                    $connector->canSynchronize(),
                    $intervalSeconds,
                ),
            ];
        }

        return $reports;
    }
}

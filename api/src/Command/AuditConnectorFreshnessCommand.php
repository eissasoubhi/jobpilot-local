<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SourceConnector;
use App\JobDiscovery\Application\ConnectorFreshnessAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:connectors:audit-freshness',
    description: 'Audit whether active connectors are still synchronizing on schedule.',
)]
final class AuditConnectorFreshnessCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConnectorFreshnessAnalyzer $freshnessAnalyzer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'interval',
            null,
            InputOption::VALUE_REQUIRED,
            'Expected synchronization interval in seconds.',
            '21600',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $interval = max(900, (int) $input->getOption('interval'));
        $connectors = $this->entityManager->getRepository(SourceConnector::class)->findBy([], ['name' => 'ASC']);

        if ($connectors === []) {
            $io->note('No connector definition is registered yet.');

            return Command::SUCCESS;
        }

        $rows = [];
        $alerts = 0;

        foreach ($connectors as $connector) {
            if (!$connector instanceof SourceConnector) {
                continue;
            }

            $freshness = $this->freshnessAnalyzer->analyze(
                $connector->getLastSyncedAt(),
                $connector->canSynchronize(),
                $interval,
            );
            if ($freshness['alert']) {
                ++$alerts;
            }

            $rows[] = [
                $connector->getCode(),
                $connector->getName(),
                $freshness['status'],
                $freshness['lastSyncedAt'] ?? '—',
                $freshness['nextExpectedAt'] ?? '—',
                (string) $freshness['overdueBySeconds'],
            ];
        }

        $io->table(
            ['Code', 'Connector', 'Freshness', 'Last sync', 'Expected by', 'Overdue (s)'],
            $rows,
        );

        if ($alerts > 0) {
            $io->error(sprintf('%d active connector(s) require attention.', $alerts));

            return Command::FAILURE;
        }

        $io->success('All active connectors are within the accepted freshness window.');

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\JobDiscovery\Application\ConnectorFreshnessReportFormatter;
use App\JobDiscovery\Application\ConnectorFreshnessReportProvider;
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
        private ConnectorFreshnessReportProvider $reportProvider,
        private ConnectorFreshnessReportFormatter $reportFormatter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'interval',
                null,
                InputOption::VALUE_REQUIRED,
                'Expected synchronization interval in seconds.',
                '21600',
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'Output format: table or json.',
                'table',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $interval = max(900, (int) $input->getOption('interval'));
        $format = strtolower(trim((string) $input->getOption('format')));
        if (!in_array($format, ['table', 'json'], true)) {
            $io->error('The output format must be table or json.');

            return Command::INVALID;
        }

        $reports = $this->reportProvider->reports($interval);
        $alerts = count(array_filter(
            $reports,
            static fn (array $report): bool => (bool) $report['alert'],
        ));

        if ($format === 'json') {
            $output->writeln($this->reportFormatter->toJson($reports, $interval));

            return $alerts > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        if ($reports === []) {
            $io->note('No connector definition is registered yet.');

            return Command::SUCCESS;
        }

        $rows = array_map(
            static fn (array $report): array => [
                $report['code'],
                $report['name'],
                $report['status'],
                $report['lastSyncedAt'] ?? '—',
                $report['nextExpectedAt'] ?? '—',
                (string) $report['overdueBySeconds'],
            ],
            $reports,
        );

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

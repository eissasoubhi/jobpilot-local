<?php

declare(strict_types=1);

namespace App\Command;

use App\JobDiscovery\Application\ConnectorFreshnessReportProvider;
use App\JobDiscovery\Infrastructure\Monitoring\ConnectorAlertWebhookNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:connectors:notify-freshness',
    description: 'Send a deduplicated webhook notification when connector freshness requires attention.',
)]
final class NotifyConnectorFreshnessCommand extends Command
{
    public function __construct(
        private ConnectorFreshnessReportProvider $reportProvider,
        private ConnectorAlertWebhookNotifier $notifier,
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

        try {
            $result = $this->notifier->notify($this->reportProvider->reports($interval), $interval);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($result === ConnectorAlertWebhookNotifier::RESULT_DISABLED) {
            $io->note('Connector alert webhook is not configured.');

            return Command::SUCCESS;
        }

        if ($result === ConnectorAlertWebhookNotifier::RESULT_NO_ALERT) {
            $io->success('No connector freshness alert requires notification.');

            return Command::SUCCESS;
        }

        if ($result === ConnectorAlertWebhookNotifier::RESULT_UNCHANGED) {
            $io->note('The current connector alert state was already notified.');

            return Command::SUCCESS;
        }

        $io->success('Connector freshness alert webhook sent.');

        return Command::SUCCESS;
    }
}

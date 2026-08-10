<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ContaminatedGmailCatalogRepairService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:jobs:repair-contaminated-gmail',
    description: 'Répare les descriptions d’offres Gmail polluées par des digests multi-offres.',
)]
final class RepairContaminatedGmailJobsCommand extends Command
{
    public function __construct(private ContaminatedGmailCatalogRepairService $repairService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal d’offres réparées par passage.', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $result = $this->repairService->repair($limit);

        $output->writeln(sprintf(
            '<info>Réparation Gmail : %d scannée(s), %d contaminée(s), %d réparée(s), %d recalculée(s).</info>',
            $result['scanned'],
            $result['contaminated'],
            $result['repaired'],
            $result['reprocessed'],
        ));
        if ($result['remainingPossible']) {
            $output->writeln('<comment>D’autres offres polluées peuvent rester ; elles seront traitées au prochain passage.</comment>');
        }

        return Command::SUCCESS;
    }
}

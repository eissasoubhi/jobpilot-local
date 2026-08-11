<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LeStudioTechJobProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scraping:smoke:le-studio-tech',
    description: 'Exécute un smoke test réseau borné et sans persistance sur Le Studio Tech.',
)]
final class LeStudioTechSmokeTestCommand extends Command
{
    private const COMPLIANCE_REVIEW_TTL_DAYS = 90;

    public function __construct(private LeStudioTechJobProvider $provider)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->provider->isConfigured()) {
            $io->error('FAIL · le connecteur Le Studio Tech est désactivé.');

            return Command::FAILURE;
        }

        $policy = $this->provider->policy();
        $reviewedAt = $policy->reviewedAt;
        $reviewDeadline = $reviewedAt?->modify('+'.self::COMPLIANCE_REVIEW_TTL_DAYS.' days');
        if (!$policy->complianceStatus->allowsAutomatedCollection()
            || $reviewedAt === null
            || $reviewDeadline === null
            || $reviewDeadline < new \DateTimeImmutable('today')) {
            $io->error('FAIL · la revue de conformité du connecteur est absente, expirée ou n’autorise plus la collecte automatisée. Aucun appel réseau n’a été effectué.');

            return Command::FAILURE;
        }

        try {
            $result = $this->provider->smokeTest();
        } catch (\Throwable) {
            $io->error('FAIL · le smoke test réseau a été refusé ou a échoué. Consulte les diagnostics contrôlés du connecteur ; aucun HTML brut n’est affiché.');

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Résultat' => $result['result']],
            ['Source' => $result['source']],
            ['Mode' => $result['mode']],
            ['Parseur' => $result['parserVersion']],
            ['Revue conformité' => $reviewedAt->format('Y-m-d')],
            ['Échéance revue' => $reviewDeadline->format('Y-m-d')],
            ['HTTP liste' => (string) $result['statusCode']],
            ['Hôte final' => $result['finalHost']],
            ['Candidats détectés' => (string) $result['candidateCount']],
            ['Fiche détail testée' => $result['detailChecked'] ? 'oui' : 'non'],
            ['HTTP détail' => $result['detailStatusCode'] === null ? 'n/a' : (string) $result['detailStatusCode']],
            ['Détail exploitable' => $result['detailExtracted'] === null ? 'n/a' : ($result['detailExtracted'] ? 'oui' : 'non')],
            ['Durée' => $result['durationMs'].' ms'],
        );

        if ($result['result'] === 'WARN') {
            $io->warning('WARN · la page publique est accessible mais aucune mission exploitable n’est publiée actuellement.');

            return Command::SUCCESS;
        }

        $io->success('PASS · le connecteur public répond et le parseur extrait une structure exploitable. Aucune offre n’a été persistée.');

        return Command::SUCCESS;
    }
}
